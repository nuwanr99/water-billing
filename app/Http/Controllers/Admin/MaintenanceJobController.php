<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MaintenanceJobStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PostJobUpdateRequest;
use App\Http\Requests\StoreMaintenanceJobRequest;
use App\Http\Requests\UpdateJobStatusRequest;
use App\Http\Requests\UpdateMaintenanceJobRequest;
use App\Libraries\Datatable;
use App\Models\Complaint;
use App\Models\MaintenanceJob;
use App\Models\User;
use App\Services\MaintenanceJobService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin maintenance-job management: create and assign work orders (linked to
 * a complaint or standalone), track them, and reassign.
 */
class MaintenanceJobController extends Controller
{
    public function __construct(protected MaintenanceJobService $jobs) {}

    /**
     * Show the maintenance-job list.
     */
    public function index(Request $request): Response
    {
        $datatable = new Datatable(
            $request,
            searchColumns: ['job_number', 'title', 'assignees.first_name', 'assignees.last_name'],
            orderColumns: ['job_number', 'status', 'scheduled_date', 'created_at'],
            defaultSort: 'scheduled_date',
            defaultDirection: 'asc',
        );

        $query = MaintenanceJob::query()
            ->with(['assignees:id,first_name,last_name', 'complaint:id,complaint_number'])
            ->when(
                in_array($request->string('status')->value(), ['assigned', 'in_progress', 'completed', 'cancelled'], true),
                fn (Builder $q) => $q->where('status', $request->string('status')->value()),
            );

        $jobs = $datatable->paginate($query)
            ->through(fn (MaintenanceJob $job): array => [
                'id' => $job->id,
                'job_number' => $job->job_number,
                'title' => $job->title,
                'status' => $job->status->value,
                'complaint_number' => $job->complaint?->complaint_number,
                'assignees' => $job->assignees->pluck('name')->all(),
                'scheduled_date' => $job->scheduled_date->format('d M Y'),
            ]);

        return Inertia::render('admin/maintenance-jobs/Index', [
            'jobs' => $jobs,
            'filters' => $datatable->filters(),
            'statusFilter' => $request->string('status')->value() ?: null,
        ]);
    }

    /**
     * Search users to assign as job assignees (any user, D-49).
     */
    public function assignees(Request $request): JsonResponse
    {
        $search = $request->string('search')->trim()->value();

        $users = User::query()
            ->when($search !== '', function (Builder $query) use ($search) {
                $term = "%{$search}%";

                $query->where(fn (Builder $sub) => $sub
                    ->orWhere('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('email', 'like', $term));
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(20)
            ->get(['id', 'first_name', 'last_name', 'email'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]);

        return response()->json($users);
    }

    /**
     * Show the create form, optionally prefilled from a complaint.
     */
    public function create(Request $request): Response
    {
        $complaint = $request->filled('complaint')
            ? Complaint::query()->findOrFail($request->integer('complaint'))
            : null;

        return Inertia::render('admin/maintenance-jobs/Create', [
            'complaint' => $complaint === null ? null : [
                'id' => $complaint->id,
                'complaint_number' => $complaint->complaint_number,
                'subject' => $complaint->subject,
            ],
        ]);
    }

    /**
     * Store a new job.
     */
    public function store(StoreMaintenanceJobRequest $request): RedirectResponse
    {
        $complaint = $request->filled('complaint_id')
            ? Complaint::query()->findOrFail($request->integer('complaint_id'))
            : null;

        $job = $this->jobs->create(
            $request->safe()->only(['title', 'description', 'scheduled_date']),
            $request->validated('assignee_ids'),
            $request->user(),
            $complaint,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Job created and assigned.')]);

        return redirect()->route('admin.maintenance-jobs.show', $job);
    }

    /**
     * Show a job with its thread and controls.
     */
    public function show(Request $request, MaintenanceJob $maintenanceJob): Response
    {
        return Inertia::render('admin/maintenance-jobs/Show', [
            'job' => $this->jobs->summary($maintenanceJob),
            'thread' => $this->jobs->thread($maintenanceJob, $request->user()),
            'can' => [
                'update' => $request->user()->can('maintenance-jobs.assign'),
                'post_update' => $this->jobs->canPostUpdate($request->user(), $maintenanceJob),
                'change_status' => $this->jobs->canUpdateStatus($request->user(), $maintenanceJob),
            ],
        ]);
    }

    /**
     * Show the edit form.
     */
    public function edit(MaintenanceJob $maintenanceJob): Response
    {
        $maintenanceJob->loadMissing(['assignees:id,first_name,last_name', 'complaint:id,complaint_number']);

        return Inertia::render('admin/maintenance-jobs/Edit', [
            'job' => [
                'id' => $maintenanceJob->id,
                'title' => $maintenanceJob->title,
                'description' => $maintenanceJob->description,
                'scheduled_date' => $maintenanceJob->scheduled_date->toDateString(),
                'complaint_number' => $maintenanceJob->complaint?->complaint_number,
                'assignees' => $maintenanceJob->assignees->map(fn (User $assignee): array => [
                    'id' => $assignee->id,
                    'name' => $assignee->name,
                ])->all(),
            ],
        ]);
    }

    /**
     * Update a job.
     */
    public function update(UpdateMaintenanceJobRequest $request, MaintenanceJob $maintenanceJob): RedirectResponse
    {
        $this->jobs->update(
            $maintenanceJob,
            $request->safe()->only(['title', 'description', 'scheduled_date']),
            $request->validated('assignee_ids'),
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Job updated.')]);

        return redirect()->route('admin.maintenance-jobs.show', $maintenanceJob);
    }

    /**
     * Post an update to the job thread.
     */
    public function postUpdate(PostJobUpdateRequest $request, MaintenanceJob $maintenanceJob): RedirectResponse
    {
        abort_unless($this->jobs->canPostUpdate($request->user(), $maintenanceJob), 403);

        $this->jobs->postUpdate(
            $maintenanceJob,
            $request->user(),
            $request->validated('body'),
            $request->file('attachments', []),
        );

        return redirect()->route('admin.maintenance-jobs.show', $maintenanceJob);
    }

    /**
     * Change a job's status (an admin may also cancel).
     */
    public function status(UpdateJobStatusRequest $request, MaintenanceJob $maintenanceJob): RedirectResponse
    {
        abort_unless($this->jobs->canUpdateStatus($request->user(), $maintenanceJob), 403);

        $this->jobs->updateStatus(
            $maintenanceJob,
            MaintenanceJobStatus::from($request->validated('status')),
            $request->validated('notes'),
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Job updated.')]);

        return redirect()->route('admin.maintenance-jobs.show', $maintenanceJob);
    }
}
