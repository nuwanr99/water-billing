<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\ComplaintMessage;
use App\Models\JobUpdate;
use App\Services\ComplaintService;
use App\Services\MaintenanceJobService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a private evidence attachment after authorizing the viewer
 * against its parent thread (D-47). Files never get public URLs.
 */
class AttachmentController extends Controller
{
    public function __construct(
        protected ComplaintService $complaints,
        protected MaintenanceJobService $jobs,
    ) {}

    /**
     * Download a single attachment.
     */
    public function __invoke(Request $request, Attachment $attachment): StreamedResponse
    {
        $attachable = $attachment->attachable;
        $user = $request->user();

        $allowed = match (true) {
            $attachable instanceof ComplaintMessage => $this->complaints->canView($user, $attachable->complaint),
            $attachable instanceof JobUpdate => $this->jobs->canView($user, $attachable->job),
            default => abort(404),
        };

        abort_unless($allowed, 403);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }
}
