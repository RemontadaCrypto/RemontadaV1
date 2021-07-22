<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['middleware' => 'api'], function () {
    Route::group(['prefix' => 'auth'], function (){
        Route::middleware(['guest:api'])->group(function () {
            Route::post('/register', [AuthController::class, 'register']);
            Route::post('/login', [AuthController::class, 'login']);
            Route::post('/email/verify', [AuthController::class, 'verifyEmailAddress']);
            Route::post('/password/reset/send-link', [AuthController::class, 'sendResetPasswordLink'])->middleware(['throttle:3,1']);
            Route::post('/password/reset/change', [AuthController::class, 'resetPassword'])->middleware(['throttle:3,1']);
        });
        Route::middleware(['auth:api'])->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/refresh', [AuthController::class, 'refresh']);
            Route::post('/user', [AuthController::class, 'user']);
            Route::post('/email/resend', [AuthController::class, 'resendEmailVerificationLink'])->middleware(['throttle:3,1']);
        });
    });
});
