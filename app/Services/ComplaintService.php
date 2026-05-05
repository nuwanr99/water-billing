<?php

namespace App\Services;

use App\Enums\ComplaintStatus;
use App\Enums\MaintenanceJobStatus;
use App\Events\ComplaintAssigned;
use App\Events\ComplaintClosed;
use App\Events\ComplaintReplied;
use App\Events\ComplaintSubmitted;
use App\Models\Attachment;
use App\Models\Complaint;
use App\Models\ComplaintMessage;
use App\Models\MaintenanceJob;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The writer and gatekeeper for complaints (spec §5.6). Every mutation runs in a
 * transaction, records an audit entry, writes to the thread, and fires the
 * after-commit event that drives WhatsApp routing (§4).
 */
class ComplaintService
{
    public function __construct(
        protected RunningNumberService $runningNumbers,
        protected AuditLogger $audit,
    ) {}

    /**
     * Submit a new complaint. The description becomes the thread's first
     * message, carrying any uploaded evidence.
     *
     * @param  array{water_account_id?: int|null, category: string, subject: string, description: string}  $data
     * @param  list<UploadedFile>  $files
     */
    public function submit(User $member, array $data, array $files = []): Complaint
    {
        $accountId = $data['water_account_id'] ?? null;

        if ($accountId !== null && ! $member->waterAccounts()->whereKey($accountId)->exists()) {
            throw ValidationException::withMessages([
                'water_account_id' => 'The selected water account does not belong to you.',
            ]);
        }

        $complaint = DB::transaction(function () use ($member, $data, $accountId, $files): Complaint {
            $complaint = Complaint::query()->create([
                'complaint_number' => $this->runningNumbers->next('complaint'),
                'user_id' => $member->id,
                'water_account_id' => $accountId,
                'category' => $data['category'],
                'subject' => $data['subject'],
                'description' => $data['description'],
                'status' => ComplaintStatus::Open,
                'submitted_at' => now(),
            ]);

            $this->postMessage($complaint, $member, $data['description'], $files);

            $this->audit->log('complaint.submitted', $complaint, [
                'complaint_number' => $complaint->complaint_number,
            ], $member);

            return $complaint;
        });

        ComplaintSubmitted::dispatch($complaint);

        return $complaint;
    }

    /**
     * Assign the complaint to one or more handlers (D-48). Newly added
     * handlers are notified; the first assignment moves open to in_progress.
     *
     * @param  list<int>  $handlerIds
     */
    public function assign(Complaint $complaint, array $handlerIds, User $actor): Complaint
    {
        $handlerIds = array_values(array_unique(array_map('intval', $handlerIds)));

        if ($handlerIds === []) {
            throw ValidationException::withMessages([
                'handler_ids' => 'Select at least one handler.',
            ]);
        }

        $newHandlerIds = DB::transaction(function () use ($complaint, $handlerIds, $actor): array {
            $current = $complaint->handlers()->pluck('users.id')->all();
            $toAdd = array_values(array_diff($handlerIds, $current));
            $toRemove = array_values(array_diff($current, $handlerIds));

            if ($toRemove !== []) {
                $complaint->handlers()->detach($toRemove);
            }

            foreach ($toAdd as $id) {
                $complaint->handlers()->attach($id, ['assigned_by' => $actor->id, 'assigned_at' => now()]);
            }

            if ($complaint->status === ComplaintStatus::Open) {
                $complaint->update(['status' => ComplaintStatus::InProgress]);
            }

            $names = User::query()->whereIn('id', $handlerIds)->get()->pluck('name')->join(', ');
            $this->postSystemMessage($complaint, "Assigned to {$names}.");

            $this->audit->log('complaint.assigned', $complaint, [
                'handler_ids' => $handlerIds,
            ], $actor);

            return $toAdd;
        });

        if ($newHandlerIds !== []) {
            ComplaintAssigned::dispatch($complaint->refresh(), $newHandlerIds);
        }

        return $complaint;
    }

    /**
     * Post a reply to the thread from either side.
     *
     * @param  list<UploadedFile>  $files
     */
    public function reply(Complaint $complaint, User $author, string $body, array $files = []): ComplaintMessage
    {
        $this->assertLive($complaint);

        $message = DB::transaction(function () use ($complaint, $author, $body, $files): ComplaintMessage {
            $message = $this->postMessage($complaint, $author, $body, $files);

            $this->audit->log('complaint.replied', $complaint, [
                'message_id' => $message->id,
            ], $author);

            return $message;
        });

        ComplaintReplied::dispatch($message);

        return $message;
    }

    /**
     * Close the complaint with a note that is delivered to the member.
     */
    public function close(Complaint $complaint, string $note, User $actor): Complaint
    {
        $this->assertLive($complaint);

        DB::transaction(function () use ($complaint, $note, $actor): void {
            $complaint->update([
                'status' => ComplaintStatus::Closed,
                'closure_note' => $note,
                'closed_by' => $actor->id,
                'closed_at' => now(),
            ]);

            $this->postSystemMessage($complaint, "Complaint closed.\n{$note}");

            $this->audit->log('complaint.closed', $complaint, [
                'closed_by' => $actor->id,
            ], $actor);
        });

        ComplaintClosed::dispatch($complaint->refresh(), $actor);

        return $complaint;
    }

    /**
     * Record on the complaint thread that a job was linked, moving an open
     * complaint into progress (D-48). Called inside the job creation
     * transaction — it does not notify (system messages are silent).
     */
    public function noteJobLinked(Complaint $complaint, MaintenanceJob $job): void
    {
        if ($complaint->status === ComplaintStatus::Open) {
            $complaint->update(['status' => ComplaintStatus::InProgress]);
        }

        $this->postSystemMessage($complaint, "Maintenance job {$job->job_number} linked.");
    }

    /**
     * Mirror a linked job's status change onto the complaint thread so the
     * ticket reflects the work's progress. Silent by design — the member
     * hears the outcome only when an admin closes the complaint (§4, step 4).
     */
    public function noteJobStatusChanged(Complaint $complaint, MaintenanceJob $job, MaintenanceJobStatus $to): void
    {
        $line = match ($to) {
            MaintenanceJobStatus::InProgress => "Maintenance job {$job->job_number} started.",
            MaintenanceJobStatus::Completed => "Maintenance job {$job->job_number} completed.",
            MaintenanceJobStatus::Cancelled => "Maintenance job {$job->job_number} cancelled.",
            default => "Maintenance job {$job->job_number} updated.",
        };

        $notes = $to === MaintenanceJobStatus::Completed ? (string) $job->completion_notes : '';

        $this->postSystemMessage($complaint, trim($notes !== '' ? "{$line}\n{$notes}" : $line));
    }

    /**
     * Create a thread message with any uploaded evidence attached.
     *
     * @param  list<UploadedFile>  $files
     */
    protected function postMessage(Complaint $complaint, User $author, string $body, array $files = []): ComplaintMessage
    {
        $message = $complaint->messages()->create([
            'user_id' => $author->id,
            'body' => $body,
        ]);

        foreach ($files as $file) {
            $path = $file->store('complaint-attachments');

            $message->attachments()->create([
                'uploaded_by' => $author->id,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        return $message;
    }

    /**
     * Write a system message (no author) into the thread.
     */
    protected function postSystemMessage(Complaint $complaint, string $body): ComplaintMessage
    {
        return $complaint->messages()->create([
            'user_id' => null,
            'body' => $body,
        ]);
    }

    /**
     * Guard that the complaint still accepts changes.
     */
    protected function assertLive(Complaint $complaint): void
    {
        if (! $complaint->status->isLive()) {
            throw ValidationException::withMessages([
                'status' => 'This complaint is closed and can no longer be updated.',
            ]);
        }
    }

    /**
     * The ticket header for the Inertia detail pages.
     *
     * @return array<string, mixed>
     */
    public function summary(Complaint $complaint): array
    {
        $complaint->loadMissing(['member:id,first_name,last_name', 'waterAccount:id,account_number,connection_address', 'handlers:id,first_name,last_name']);

        return [
            'id' => $complaint->id,
            'complaint_number' => $complaint->complaint_number,
            'subject' => $complaint->subject,
            'category' => $complaint->category->value,
            'status' => $complaint->status->value,
            'member' => [
                'id' => $complaint->member->id,
                'name' => $complaint->member->name,
            ],
            'water_account' => $complaint->waterAccount === null ? null : [
                'id' => $complaint->waterAccount->id,
                'account_number' => $complaint->waterAccount->account_number,
                'connection_address' => $complaint->waterAccount->connection_address,
            ],
            'handlers' => $complaint->handlers->map(fn (User $handler): array => [
                'id' => $handler->id,
                'name' => $handler->name,
            ])->all(),
            'closure_note' => $complaint->closure_note,
            'submitted_at' => $complaint->submitted_at->format('d M Y, g:i A'),
            'closed_at' => $complaint->closed_at?->format('d M Y, g:i A'),
        ];
    }

    /**
     * The maintenance jobs spun off this complaint, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function jobs(Complaint $complaint): array
    {
        return $complaint->jobs()
            ->with('assignees:id,first_name,last_name')
            ->latest()
            ->get()
            ->map(fn (MaintenanceJob $job): array => [
                'id' => $job->id,
                'job_number' => $job->job_number,
                'title' => $job->title,
                'status' => $job->status->value,
                'assignees' => $job->assignees->pluck('name')->all(),
                'scheduled_date' => $job->scheduled_date->format('d M Y'),
            ])
            ->all();
    }

    /**
     * The conversation, oldest first, from the viewer's perspective.
     *
     * @return list<array<string, mixed>>
     */
    public function thread(Complaint $complaint, User $viewer): array
    {
        $complaint->loadMissing(['messages.author:id,first_name,last_name', 'messages.attachments']);

        return $complaint->messages->map(fn (ComplaintMessage $message): array => [
            'id' => $message->id,
            'body' => $message->body,
            'is_system' => $message->isSystem(),
            'is_mine' => $message->user_id === $viewer->id,
            'author' => $message->author?->name,
            'created_at' => $message->created_at?->format('d M Y, g:i A'),
            'attachments' => $message->attachments->map(fn (Attachment $attachment): array => [
                'id' => $attachment->id,
                'name' => $attachment->original_name,
                'is_image' => $attachment->isImage(),
                'url' => route('attachments.download', $attachment->id),
            ])->all(),
        ])->all();
    }

    /**
     * Whether the user may view this complaint: the owning member, a handler,
     * a linked-job assignee, or staff allowed to see all complaints.
     */
    public function canView(User $user, Complaint $complaint): bool
    {
        return $this->owns($user, $complaint)
            || $user->can('complaints.view-all')
            || $this->handles($user, $complaint)
            || $this->assignedToLinkedJob($user, $complaint);
    }

    /**
     * Whether the user may reply to this live complaint.
     */
    public function canReply(User $user, Complaint $complaint): bool
    {
        return $complaint->status->isLive() && (
            $this->owns($user, $complaint)
            || $this->handles($user, $complaint)
            || $this->assignedToLinkedJob($user, $complaint)
            || $user->can('complaints.manage')
        );
    }

    /**
     * Whether the user may assign handlers.
     */
    public function canAssign(User $user, Complaint $complaint): bool
    {
        return $user->can('complaints.manage');
    }

    /**
     * Whether the user may close this live complaint: the owning member
     * (self-close) or a manager.
     */
    public function canClose(User $user, Complaint $complaint): bool
    {
        return $complaint->status->isLive()
            && ($this->owns($user, $complaint) || $user->can('complaints.manage'));
    }

    /**
     * Whether the user submitted the complaint.
     */
    protected function owns(User $user, Complaint $complaint): bool
    {
        return $complaint->user_id === $user->id;
    }

    /**
     * Whether the user is one of the complaint's handlers.
     */
    protected function handles(User $user, Complaint $complaint): bool
    {
        return $complaint->handlers()->whereKey($user->id)->exists();
    }

    /**
     * Whether the user is assigned to a live job spun off this complaint —
     * such assignees may read and reply to it.
     */
    protected function assignedToLinkedJob(User $user, Complaint $complaint): bool
    {
        return $complaint->jobs()
            ->whereIn('status', ['assigned', 'in_progress'])
            ->whereHas('assignees', fn ($query) => $query->whereKey($user->id))
            ->exists();
    }
}
