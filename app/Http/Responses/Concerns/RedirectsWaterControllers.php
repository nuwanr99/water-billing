<?php

namespace App\Http\Responses\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait RedirectsWaterControllers
{
    /**
     * A Water Controller signing in from a mobile device lands directly on
     * the meter reading field interface; anyone else gets null and falls
     * through to the default Fortify redirect.
     */
    protected function waterControllerRedirect(Request $request): ?RedirectResponse
    {
        $user = $request->user();

        if ($user === null || ! $user->hasRole('Water Controller')) {
            return null;
        }

        if (preg_match('/Android|iPhone|iPad|Mobile/i', (string) $request->userAgent()) !== 1) {
            return null;
        }

        return redirect()->route('meter-readings.index');
    }
}
