<?php

namespace App\Services;

use App\Enums\MaintenanceJobStatus;
use App\Events\MaintenanceJobAssigned;
use App\Events\MaintenanceJobCancelled;
use App\Events\MaintenanceJobCompleted;
use App\Events\MaintenanceJobUpdated;
use App\Models\Attachment;
use App\Models\Complaint;
use App\Models\JobUpdate;
use App\Models\MaintenanceJob;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The sole writer for maintenance jobs (spec §5.6, D-04). Works standalone
 * for ad-hoc jobs and, when linked to a complaint, drives the complaint's
 * status and thread through ComplaintService. Every mutation is transactional,
 * audited, threaded, and fires the after-commit event behind WhatsApp routing.
 */
class MaintenanceJobService
{
    /**
     * The legal status transitions for a job.
     *
     * @var array<string, list<MaintenanceJobStatus>>
     */
    protected const array TRANSITIONS = [
        'assigned' => [MaintenanceJobStatus::InProgress, MaintenanceJobStatus::Cancelled],
        'in_progress' => [MaintenanceJobStatus::Completed, MaintenanceJobStatus::Cancelled],
    ];

    public function __construct(
        protected RunningNumberService $runningNumbers,
        protected AuditLogger $audit,
        protected ComplaintService $complaints,
    ) {}

    /**
     * Create a job and assign it. When a complaint is given it must be live;
     * the complaint moves into progress and gets a thread note (D-48).
     *
     * @param  array{title: string, description: string, scheduled_date: string}  $data
     * @param  list<int>  $assigneeIds
     */
    public function create(array $data, array $assigneeIds, User $creator, ?Complaint $complaint = null): MaintenanceJob
    {
        $assigneeIds = $this->normalizeIds($assigneeIds);

        if ($assigneeIds === []) {
            throw ValidationException::withMessages(['assignee_ids' => 'Assign the job to at least one person.']);
        }

        if ($complaint !== null && ! $complaint->status->isLive()) {
            throw ValidationException::withMessages(['complaint_id' => 'This complaint is closed and cannot take new jobs.']);
        }

        $job = DB::transaction(function () use ($data, $assigneeIds, $creator, $complaint): MaintenanceJob {
            $job = MaintenanceJob::query()->create([
                'job_number' => $this->runningNumbers->next('job'),
                'complaint_id' => $complaint?->id,
                'created_by' => $creator->id,
                'title' => $data['title'],
                'description' => $data['description'],
                'scheduled_date' => $data['scheduled_date'],
                'status' => MaintenanceJobStatus::Assigned,
            ]);

            foreach ($assigneeIds as $id) {
                $job->assignees()->attach($id, ['assigned_by' => $creator->id, 'assigned_at' => now()]);
            }

            if ($complaint !== null) {
                $this->complaints->noteJobLinked($complaint, $job);
            }

            $this->audit->log('job.created', $job, ['job_number' => $job->job_number, 'complaint_id' => $complaint?->id], $creator);

            return $job;
        });

        MaintenanceJobAssigned::dispatch($job->refresh(), $assigneeIds);

        return $job;
    }

    /**
     * Edit, reschedule, or reassign a job. Newly added assignees are notified.
     *
     * @param  array{title: string, description: string, scheduled_date: string}  $data
     * @param  list<int>  $assigneeIds
     */
    public function update(MaintenanceJob $job, array $data, array $assigneeIds, User $actor): MaintenanceJob
    {
        $assigneeIds = $this->normalizeIds($assigneeIds);

        if ($assigneeIds === []) {
            throw ValidationException::withMessages(['assignee_ids' => 'Assign the job to at least one person.']);
        }

        $newAssigneeIds = DB::transaction(function () use ($job, $data, $assigneeIds, $actor): array {
            $job->update([
                'title' => $data['title'],
                'description' => $data['description'],
                'scheduled_date' => $data['scheduled_date'],
            ]);

            $current = $job->assignees()->pluck('users.id')->all();
            $toAdd = array_values(array_diff($assigneeIds, $current));
            $toRemove = array_values(array_diff($current, $assigneeIds));

            if ($toRemove !== []) {
                $job->assignees()->detach($toRemove);
            }

            foreach ($toAdd as $id) {
                $job->assignees()->attach($id, ['assigned_by' => $actor->id, 'assigned_at' => now()]);
            }

            $this->audit->log('job.updated', $job, ['assignee_ids' => $assigneeIds], $actor);

            return $toAdd;
        });

        if ($newAssigneeIds !== []) {
            MaintenanceJobAssigned::dispatch($job->refresh(), $newAssigneeIds);
        }

        return $job;
    }

    /**
     * Post an update to the job thread with any uploaded evidence.
     *
     * @param  list<UploadedFile>  $files
     */
    public function postUpdate(MaintenanceJob $job, User $author, string $body, array $files = []): JobUpdate
    {
        $this->assertLive($job);

        $update = DB::transaction(function () use ($job, $author, $body, $files): JobUpdate {
            $update = $job->updates()->create(['user_id' => $author->id, 'body' => $body]);

            foreach ($files as $file) {
                $path = $file->store('job-attachments');

                $update->attachments()->create([
                    'uploaded_by' => $author->id,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ]);
            }

            $this->audit->log('job.update-posted', $job, ['update_id' => $update->id], $author);

            return $update;
        });

        MaintenanceJobUpdated::dispatch($update);

        return $update;
    }

    /**
     * Move a job along its lifecycle. Completing and cancelling require notes;
     * completing a linked job records the resolution on the complaint thread.
     */
    public function updateStatus(MaintenanceJob $job, MaintenanceJobStatus $to, ?string $notes, User $actor): MaintenanceJob
    {
        $this->assertTransition($job, $to);

        if (($to === MaintenanceJobStatus::Completed || $to === MaintenanceJobStatus::Cancelled) && blank($notes)) {
            throw ValidationException::withMessages(['notes' => 'A note is required to complete or cancel a job.']);
        }

        DB::transaction(function () use ($job, $to, $notes, $actor): void {
            $job->update([
                'status' => $to,
                'completion_notes' => in_array($to, [MaintenanceJobStatus::Completed, MaintenanceJobStatus::Cancelled], true) ? $notes : $job->completion_notes,
                'completed_at' => $to === MaintenanceJobStatus::Completed ? now() : $job->completed_at,
            ]);

            $job->updates()->create(['user_id' => null, 'body' => $this->transitionNote($to, $notes)]);

            if ($job->complaint !== null) {
                $this->complaints->noteJobStatusChanged($job->complaint, $job, $to);
            }

            $this->audit->log("job.{$to->value}", $job, [], $actor);
        });

        $job->refresh();

        if ($to === MaintenanceJobStatus::Completed) {
            MaintenanceJobCompleted::dispatch($job);
        }

        if ($to === MaintenanceJobStatus::Cancelled) {
            MaintenanceJobCancelled::dispatch($job, $notes);
        }

        return $job;
    }

    /**
     * The system-thread line for a status change.
     */
    protected function transitionNote(MaintenanceJobStatus $to, ?string $notes): string
    {
        $line = match ($to) {
            MaintenanceJobStatus::InProgress => 'Job started.',
            MaintenanceJobStatus::Completed => 'Job completed.',
            MaintenanceJobStatus::Cancelled => 'Job cancelled.',
            MaintenanceJobStatus::Assigned => 'Job assigned.',
        };

        return trim($notes ? "{$line}\n{$notes}" : $line);
    }

    /**
     * Guard that a status change is legal.
     */
    protected function assertTransition(MaintenanceJob $job, MaintenanceJobStatus $to): void
    {
        $allowed = self::TRANSITIONS[$job->status->value] ?? [];

        if (! in_array($to, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "A {$job->status->value} job cannot move to {$to->value}.",
            ]);
        }
    }

    /**
     * Guard that the job still accepts work.
     */
    protected function assertLive(MaintenanceJob $job): void
    {
        if (! $job->status->isLive()) {
            throw ValidationException::withMessages(['status' => 'This job is closed and can no longer be updated.']);
        }
    }

    /**
     * Normalize an id list to unique integers.
     *
     * @param  list<int|string>  $ids
     * @return list<int>
     */
    protected function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * The job header for the Inertia detail pages.
     *
     * @return array<string, mixed>
     */
    public function summary(MaintenanceJob $job): array
    {
        $job->loadMissing([
            'creator:id,first_name,last_name',
            'assignees:id,first_name,last_name',
            'complaint:id,complaint_number,subject,water_account_id',
            'complaint.waterAccount:id,account_number,connection_address',
        ]);

        return [
            'id' => $job->id,
            'job_number' => $job->job_number,
            'title' => $job->title,
            'description' => $job->description,
            'status' => $job->status->value,
            'scheduled_date' => $job->scheduled_date->format('d M Y'),
            'completion_notes' => $job->completion_notes,
            'completed_at' => $job->completed_at?->format('d M Y, g:i A'),
            'created_by' => $job->creator->name,
            'assignees' => $job->assignees->map(fn (User $assignee): array => [
                'id' => $assignee->id,
                'name' => $assignee->name,
            ])->all(),
            'complaint' => $job->complaint === null ? null : [
                'id' => $job->complaint->id,
                'complaint_number' => $job->complaint->complaint_number,
                'subject' => $job->complaint->subject,
                'account_number' => $job->complaint->waterAccount?->account_number,
                'connection_address' => $job->complaint->waterAccount?->connection_address,
            ],
        ];
    }

    /**
     * The job thread, oldest first, from the viewer's perspective.
     *
     * @return list<array<string, mixed>>
     */
    public function thread(MaintenanceJob $job, User $viewer): array
    {
        $job->loadMissing(['updates.author:id,first_name,last_name', 'updates.attachments']);

        return $job->updates->map(fn (JobUpdate $update): array => [
            'id' => $update->id,
            'body' => $update->body,
            'is_system' => $update->isSystem(),
            'is_mine' => $update->user_id === $viewer->id,
            'author' => $update->author?->name,
            'created_at' => $update->created_at?->format('d M Y, g:i A'),
            'attachments' => $update->attachments->map(fn (Attachment $attachment): array => [
                'id' => $attachment->id,
                'name' => $attachment->original_name,
                'is_image' => $attachment->isImage(),
                'url' => route('attachments.download', $attachment->id),
            ])->all(),
        ])->all();
    }

    /**
     * Whether the user may view this job: an assignee, the creator, or staff
     * allowed to see all jobs.
     */
    public function canView(User $user, MaintenanceJob $job): bool
    {
        return $this->assigned($user, $job)
            || $job->created_by === $user->id
            || $user->can('maintenance-jobs.view-all');
    }

    /**
     * Whether the user may post an update to this live job.
     */
    public function canPostUpdate(User $user, MaintenanceJob $job): bool
    {
        return $job->status->isLive() && (
            $this->assigned($user, $job)
            || $job->created_by === $user->id
            || $user->can('maintenance-jobs.view-all')
        );
    }

    /**
     * Whether the user may change this live job's status: an assignee
     * (start/complete) or a job admin (who may also cancel).
     */
    public function canUpdateStatus(User $user, MaintenanceJob $job): bool
    {
        return $job->status->isLive()
            && ($this->assigned($user, $job) || $user->can('maintenance-jobs.assign'));
    }

    /**
     * Whether the user is one of the job's assignees.
     */
    protected function assigned(User $user, MaintenanceJob $job): bool
    {
        return $job->assignees()->whereKey($user->id)->exists();
    }
}
