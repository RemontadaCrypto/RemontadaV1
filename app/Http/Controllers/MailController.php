<?php

namespace App\Http\Controllers;

use App\Notifications\CustomEmailNotification;
use Illuminate\Http\Request;

class MailController extends Controller
{
    public static function sendEmailVerificationLink($user, $otp)
    {
        $line1 = 'Please click the button below to verify your email address.';
        $line2 = 'If you did not create an account, no further action is required.';
        $url = env('FRONTEND_URL').'/email/verify?email='.$user->email.'&otp='.$otp;
        $user->notify(new CustomEmailNotification('Verify Email Address', $line1, $line2, 'Verify Email Address', $url));
    }

    public static function sendPasswordResetPasswordLink($user, $token)
    {
        $line1 = 'You are receiving this email because we received a password reset request for your account.';
        $line2 = 'This password reset link will expire in 60 minutes.<br><br>If you did not request a password reset, no further action is required.';
        $url = env('FRONTEND_URL').'/password/change?email='.$user->email.'&token='.$token;
        $user->notify(new CustomEmailNotification('Reset Password Notification', $line1, $line2, 'Reset Password', $url));
    }
}
