<?php

namespace App\Support;

use App\Models\Attachment;
use App\Models\Complaint;
use App\Models\ComplaintMessage;
use App\Models\User;

/**
 * Shapes complaints for the Inertia pages so the member and admin views
 * present the same ticket consistently.
 */
class ComplaintPresenter
{
    /**
     * The ticket header shared by every detail view.
     *
     * @return array<string, mixed>
     */
    public static function summary(Complaint $complaint): array
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
     * The conversation, oldest first, from the viewer's perspective.
     *
     * @return list<array<string, mixed>>
     */
    public static function thread(Complaint $complaint, User $viewer): array
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
}
