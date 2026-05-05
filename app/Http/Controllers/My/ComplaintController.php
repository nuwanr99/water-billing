<?php

namespace App\Http\Controllers\My;

use App\Enums\ComplaintCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReplyComplaintRequest;
use App\Http\Requests\StoreComplaintRequest;
use App\Models\Complaint;
use App\Services\ComplaintService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The member-facing complaint portal: submit a complaint, follow the
 * thread, reply with evidence, and close it.
 */
class ComplaintController extends Controller
{
    public function __construct(protected ComplaintService $complaints) {}

    /**
     * List the member's own complaints, newest activity first.
     */
    public function index(Request $request): Response
    {
        $complaints = $request->user()->complaints()
            ->with('waterAccount:id,account_number')
            ->withCount('messages')
            ->latest('submitted_at')
            ->paginate(10)
            ->through(fn (Complaint $complaint): array => [
                'id' => $complaint->id,
                'complaint_number' => $complaint->complaint_number,
                'subject' => $complaint->subject,
                'category' => $complaint->category->value,
                'status' => $complaint->status->value,
                'account_number' => $complaint->waterAccount?->account_number,
                'submitted_at' => $complaint->submitted_at->format('d M Y'),
            ]);

        return Inertia::render('my/complaints/Index', [
            'complaints' => $complaints,
        ]);
    }

    /**
     * Show the submission form with the member's own accounts.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('my/complaints/Create', [
            'accounts' => $request->user()->waterAccounts
                ->sortBy('account_number')
                ->values()
                ->map(fn ($account): array => [
                    'id' => $account->id,
                    'account_number' => $account->account_number,
                    'connection_address' => $account->connection_address,
                ]),
            'categories' => $this->categoryOptions(),
        ]);
    }

    /**
     * Store a new complaint and open its thread.
     */
    public function store(StoreComplaintRequest $request): RedirectResponse
    {
        $complaint = $this->complaints->submit(
            $request->user(),
            $request->safe()->only(['water_account_id', 'category', 'subject', 'description']),
            $request->file('attachments', []),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Complaint submitted.')]);

        return redirect()->route('my.complaints.show', $complaint);
    }

    /**
     * Show a complaint's thread to its owner.
     */
    public function show(Request $request, Complaint $complaint): Response
    {
        abort_unless($this->complaints->canView($request->user(), $complaint), 403);

        return Inertia::render('my/complaints/Show', [
            'complaint' => $this->complaints->summary($complaint),
            'thread' => $this->complaints->thread($complaint, $request->user()),
            'can' => [
                'reply' => $this->complaints->canReply($request->user(), $complaint),
                'close' => $this->complaints->canClose($request->user(), $complaint),
            ],
        ]);
    }

    /**
     * Post a reply from the member.
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

        return redirect()->route('my.complaints.show', $complaint);
    }

    /**
     * Let the member close their own complaint.
     */
    public function close(Request $request, Complaint $complaint): RedirectResponse
    {
        abort_unless($this->complaints->canClose($request->user(), $complaint), 403);

        $validated = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        $this->complaints->close($complaint, $validated['note'] ?? '', $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Complaint closed.')]);

        return redirect()->route('my.complaints.show', $complaint);
    }

    /**
     * The category select options with their Sinhala labels.
     *
     * @return list<array{value: string, label: string}>
     */
    protected function categoryOptions(): array
    {
        return array_map(fn (ComplaintCategory $category): array => [
            'value' => $category->value,
            'label' => $category->label(),
        ], ComplaintCategory::cases());
    }
}
