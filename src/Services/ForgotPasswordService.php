<?php

namespace Binaryk\LaravelRestify\Services;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPasswordService
{
    public static function make(Request $request, string $url = null)
    {
        ResetPassword::createUrlUsing(function ($notifiable, $token) use ($url) {
            $withToken = str_replace(['{token}'], $token, $url ?? config('restify.auth.password_reset_url'));
            $withEmail = str_replace(['{email}'], $notifiable->getEmailForPasswordReset(), $withToken);

            return url($withEmail);
        });

        return resolve(static::class)->sendResetLinkEmail($request);
    }

    /**
     * Send a reset link to the given user.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $response = $this->broker()->sendResetLink(
            $request->only('email')
        );

        return $response == Password::RESET_LINK_SENT
            ? $this->sendResetLinkResponse($response)
            : $this->sendResetLinkFailedResponse($request, $response);
    }

    public function broker()
    {
        return Password::broker();
    }

    /**
     * Get the response for a successful password reset link.
     *
     * @param string $response
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function sendResetLinkResponse($response)
    {
        return response()->json([
            'status' => trans($response),
        ]);
    }

    /**
     * Get the response for a failed password reset link.
     *
     * @param \Illuminate\Http\Request
     * @param string $response
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function sendResetLinkFailedResponse(Request $request, $response)
    {
        return response()->json([
            'errors' => [
                'email' => [
                    trans($response),
                ],
            ],
        ])->setStatusCode(400);
    }
}
