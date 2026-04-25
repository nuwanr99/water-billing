<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\ComplaintMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a private evidence attachment after authorizing the viewer
 * against its parent thread (D-47). Files never get public URLs.
 */
class AttachmentController extends Controller
{
    /**
     * Download a single attachment.
     */
    public function __invoke(Request $request, Attachment $attachment): StreamedResponse
    {
        $attachable = $attachment->attachable;

        if ($attachable instanceof ComplaintMessage) {
            $this->authorize('view', $attachable->complaint);
        } else {
            abort(404);
        }

        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }
}
