<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignComplaintRequest;
use App\Http\Requests\ReplyComplaintRequest;
use App\Libraries\Datatable;
use App\Models\Complaint;
use App\Models\User;
use App\Services\ComplaintService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The admin complaint queue: triage, assign handlers, reply, and close.
 */
class ComplaintController extends Controller
{
    public function __construct(protected ComplaintService $complaints) {}

    /**
     * Show the complaint queue.
     */
    public function index(Request $request): Response
    {
        $datatable = new Datatable(
            $request,
            searchColumns: ['complaint_number', 'subject', 'member.first_name', 'member.last_name'],
            orderColumns: ['complaint_number', 'status', 'submitted_at', 'created_at'],
            defaultSort: 'submitted_at',
            defaultDirection: 'desc',
        );

        $query = Complaint::query()
            ->with(['member:id,first_name,last_name', 'waterAccount:id,account_number', 'handlers:id,first_name,last_name'])
            ->when(
                in_array($request->string('status')->value(), ['open', 'in_progress', 'closed'], true),
                fn (Builder $q) => $q->where('status', $request->string('status')->value()),
            );

        $complaints = $datatable->paginate($query)
            ->through(fn (Complaint $complaint): array => [
                'id' => $complaint->id,
                'complaint_number' => $complaint->complaint_number,
                'subject' => $complaint->subject,
                'category' => $complaint->category->value,
                'status' => $complaint->status->value,
                'member' => $complaint->member->name,
                'account_number' => $complaint->waterAccount?->account_number,
                'handlers' => $complaint->handlers->pluck('name')->all(),
                'submitted_at' => $complaint->submitted_at->format('d M Y'),
            ]);

        return Inertia::render('admin/complaints/Index', [
            'complaints' => $complaints,
            'filters' => $datatable->filters(),
            'statusFilter' => $request->string('status')->value() ?: null,
        ]);
    }

    /**
     * Search users to assign as handlers.
     */
    public function handlers(Request $request): JsonResponse
    {
        $search = $request->string('search')->trim()->value();

        $handlers = User::query()
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

        return response()->json($handlers);
    }

    /**
     * Show the full complaint with its thread and controls.
     */
    public function show(Request $request, Complaint $complaint): Response
    {
        return Inertia::render('admin/complaints/Show', [
            'complaint' => $this->complaints->summary($complaint),
            'thread' => $this->complaints->thread($complaint, $request->user()),
            'jobs' => $this->complaints->jobs($complaint),
            'can' => [
                'assign' => $this->complaints->canAssign($request->user(), $complaint),
                'reply' => $this->complaints->canReply($request->user(), $complaint),
                'close' => $this->complaints->canClose($request->user(), $complaint),
                'create_job' => $request->user()->can('maintenance-jobs.create') && $complaint->status->isLive(),
            ],
        ]);
    }

    /**
     * Assign one or more handlers to the complaint.
     */
    public function assign(AssignComplaintRequest $request, Complaint $complaint): RedirectResponse
    {
        abort_unless($this->complaints->canAssign($request->user(), $complaint), 403);

        $this->complaints->assign($complaint, $request->validated('handler_ids'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Complaint assigned.')]);

        return redirect()->route('admin.complaints.show', $complaint);
    }

    /**
     * Post a staff reply to the complaint thread.
     */
    public function reply(ReplyComplaintRequest $request, Complaint $complaint): RedirectResponse
    {
        abort_unless($this->complaints->canReply($request->user(), $complaint), 403);

        $this->complaints->reply(
            $complaint,
            $request->user(),
            $request->validated('body'),
            $request->file('attachments', []),
        );

        return redirect()->route('admin.complaints.show', $complaint);
    }

    /**
     * Close the complaint with a note delivered to the member.
     */
    public function close(Request $request, Complaint $complaint): RedirectResponse
    {
        abort_unless($this->complaints->canClose($request->user(), $complaint), 403);

        $validated = $request->validate(['note' => ['required', 'string', 'max:2000']]);

        $this->complaints->close($complaint, $validated['note'], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Complaint closed.')]);

        return redirect()->route('admin.complaints.show', $complaint);
    }
}
