<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use function PHPUnit\Framework\isInstanceOf;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $e)
    {
        if ($request->expectsJson()){
            if ($e instanceof ModelNotFoundException){
                return response()->json(['errors' => 'Model Not Found'], 404);
            }
            if ($e instanceof MethodNotAllowedHttpException){
                return response()->json(['errors' => 'Method Not Allowed'], 405);
            }
            if ($e instanceof NotFoundHttpException){
                return response()->json(['errors' => 'Invalid Route'], 404);
            }
            if ($e instanceof ThrottleRequestsException){
                return response()->json(['errors' => 'Too Many Requests'], 429);
            }
            if ($e instanceof AccessDeniedHttpException) {
                return response()->json(['errors' => 'This action is unauthorized'], 403);
            }
            if ($e instanceof ConnectionException) {
                return response()->json(['errors' => 'An error occurred'], 400);
            }
        }
        return parent::render($request, $e);
    }
}
