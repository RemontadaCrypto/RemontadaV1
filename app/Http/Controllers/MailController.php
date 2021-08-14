<?php

namespace App\Http\Controllers;

use App\Notifications\CustomEmailNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

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

    public static function sendWithdrawalTransactionMail($user, $payload)
    {
        $line1 = 'Your withdrawal of <b>'.number_format($payload['amount'], 9).' '.strtoupper($payload['coin']['short_name']).'</b> to <b>'.$payload['party'].'</b> was successful';
        $user->notify(new CustomEmailNotification('Withdrawal Transaction', $line1));
    }

    public static function sendBuyerTransactionMail($user, $payload)
    {
        $line1 = 'Your have successfully bought <b>'.number_format($payload['trade']['amount_in_coin'], 9).' '.strtoupper($payload['coin']['short_name']).'</b> from <b>'.$payload['trade']['seller']['name'].'</b> at ₦'.number_format($payload['trade']['amount_in_ngn']).' at the rate of ₦'.$payload['trade']['offer']['rate'].' per USD';
        $user->notify(new CustomEmailNotification('Trade '.$payload['trade']['ref'].' Successful', $line1));
    }

    public static function sendSellerTransactionMail($user, $payload)
    {
        $line1 = 'Your have successfully sold <b>'.number_format($payload['trade']['amount_in_coin'], 9).' '.strtoupper($payload['coin']['short_name']).'</b> to <b>'.$payload['trade']['buyer']['name'].'</b> at ₦'.number_format($payload['trade']['amount_in_ngn']).' at the rate of ₦'.$payload['trade']['offer']['rate'].' per USD';
        $user->notify(new CustomEmailNotification('Trade '.$payload['trade']['ref'].' Successful', $line1));
    }
}
