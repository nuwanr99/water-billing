<?php

namespace App\Services;

use App\Enums\ComplaintStatus;
use App\Events\ComplaintAssigned;
use App\Events\ComplaintClosed;
use App\Events\ComplaintReplied;
use App\Events\ComplaintSubmitted;
use App\Models\Complaint;
use App\Models\ComplaintMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The sole writer for complaints (spec §5.6). Every mutation runs in a
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
            $this->postSystemMessage($complaint, "පැමිණිල්ල {$names} වෙත පවරන ලදී.");

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

            $this->postSystemMessage($complaint, "පැමිණිල්ල වසා දමන ලදී.\n{$note}");

            $this->audit->log('complaint.closed', $complaint, [
                'closed_by' => $actor->id,
            ], $actor);
        });

        ComplaintClosed::dispatch($complaint->refresh(), $actor);

        return $complaint;
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
}
