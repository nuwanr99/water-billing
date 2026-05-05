<?php

namespace App\Http\Controllers\My;

use App\Enums\MaintenanceJobStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PostJobUpdateRequest;
use App\Http\Requests\UpdateJobStatusRequest;
use App\Models\MaintenanceJob;
use App\Services\MaintenanceJobService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The assignee-facing job view: see jobs assigned to you, post updates with
 * evidence, and move them from assigned to in progress to completed.
 */
class JobController extends Controller
{
    public function __construct(protected MaintenanceJobService $jobs) {}

    /**
     * List the jobs assigned to the current user.
     */
    public function index(Request $request): Response
    {
        $jobs = $request->user()->assignedJobs()
            ->with('complaint:id,complaint_number')
            ->orderByRaw("case status when 'in_progress' then 0 when 'assigned' then 1 else 2 end")
            ->orderBy('scheduled_date')
            ->get()
            ->map(fn (MaintenanceJob $job): array => [
                'id' => $job->id,
                'job_number' => $job->job_number,
                'title' => $job->title,
                'status' => $job->status->value,
                'complaint_number' => $job->complaint?->complaint_number,
                'scheduled_date' => $job->scheduled_date->format('d M Y'),
            ]);

        return Inertia::render('my-jobs/Index', [
            'jobs' => $jobs,
        ]);
    }

    /**
     * Show a job assigned to the current user.
     */
    public function show(Request $request, MaintenanceJob $maintenanceJob): Response
    {
        abort_unless($this->jobs->canView($request->user(), $maintenanceJob), 403);

        return Inertia::render('my-jobs/Show', [
            'job' => $this->jobs->summary($maintenanceJob),
            'thread' => $this->jobs->thread($maintenanceJob, $request->user()),
            'can' => [
                'post_update' => $this->jobs->canPostUpdate($request->user(), $maintenanceJob),
                'change_status' => $this->jobs->canUpdateStatus($request->user(), $maintenanceJob),
            ],
        ]);
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

        return redirect()->route('my-jobs.show', $maintenanceJob);
    }

    /**
     * Start or complete the job.
     */
    public function updateStatus(UpdateJobStatusRequest $request, MaintenanceJob $maintenanceJob): RedirectResponse
    {
        abort_unless($this->jobs->canUpdateStatus($request->user(), $maintenanceJob), 403);

        $this->jobs->updateStatus(
            $maintenanceJob,
            MaintenanceJobStatus::from($request->validated('status')),
            $request->validated('notes'),
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Job updated.')]);

        return redirect()->route('my-jobs.show', $maintenanceJob);
    }
}
