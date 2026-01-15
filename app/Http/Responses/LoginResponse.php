<?php

namespace App\Http\Responses;

use App\Http\Responses\Concerns\RedirectsWaterControllers;
use Illuminate\Http\Request;
use Laravel\Fortify\Http\Responses\LoginResponse as FortifyLoginResponse;

class LoginResponse extends FortifyLoginResponse
{
    use RedirectsWaterControllers;

    /**
     * Create an HTTP response that represents the object.
     *
     * @param  Request  $request
     */
    public function toResponse($request): mixed
    {
        return $this->waterControllerRedirect($request) ?? parent::toResponse($request);
    }
}
