<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DefaultController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\TradeController;
use App\Http\Controllers\TransactionController;
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
            Route::put('/password/update', [AuthController::class, 'updatePassword']);
        });
    });

    Route::middleware(['auth:api'])->group(function (){
        Route::group(['prefix' => 'balance'], function () {
            Route::get('/all', [AddressController::class, 'getAllBalance']);
            Route::get('/{coin:short_name}', [AddressController::class, 'getBalanceByCoin']);
        });
        Route::group(['prefix' => 'transactions'], function () {
            Route::post('/{coin:short_name}/withdraw', [TransactionController::class, 'withdraw']);
            Route::get('/{coin:short_name}', [TransactionController::class, 'getTransactionByCoin']);
        });
        Route::group(['prefix' => 'coins'], function () {
            Route::get('/', [DefaultController::class, 'getCoins']);
            Route::get('/{coin:short_name}/show', [DefaultController::class, 'showCoin']);
        });
        Route::group(['prefix' => 'offers'], function () {
            Route::get('/', [OfferController::class, 'index']);
            Route::get('/user', [OfferController::class, 'userOffers']);
            Route::post('/store', [OfferController::class, 'store']);
            Route::get('/{offer}/show', [OfferController::class, 'show']);
            Route::put('/{offer}/update', [OfferController::class, 'update']);
            Route::put('/{offer}/close', [OfferController::class, 'close']);
            Route::delete('/{offer}/delete', [OfferController::class, 'destroy']);
        });
        Route::group(['prefix' => 'trades'], function () {
            Route::get('/user', [TradeController::class, 'fetchUserTrades']);
            Route::post('/initiate', [TradeController::class, 'initiate']);
            Route::get('/{trade}/show', [TradeController::class, 'show']);
            Route::post('/{trade}/accept', [TradeController::class, 'accept']);
            Route::post('/{trade}/make-payment', [TradeController::class, 'makePayment']);
            Route::post('/{trade}/confirm-payment', [TradeController::class, 'confirmPayment']);
            Route::post('/{trade}/cancel', [TradeController::class, 'cancel']);
        });
    });
});
