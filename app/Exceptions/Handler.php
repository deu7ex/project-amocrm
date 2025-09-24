<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
use Illuminate\Auth\AuthenticationException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
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

    public function render($request, Throwable $exception)
    {
        if ($exception instanceof ValidationException) {
            return response()->json([
                'error' => 'Validation Failed',
                'messages' => $exception->errors(),
            ], 422, [], JSON_UNESCAPED_UNICODE);
        }

        if ($exception instanceof AuthenticationException) {
            return response()->json([
                'error' => 'Unauthenticated',
                'message' => 'Unauthenticated. Токен не передан или недействителен.',
            ], 401, [], JSON_UNESCAPED_UNICODE);
        }

        if ($exception instanceof ModelNotFoundException) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Запись не найдена.',
            ], 404, [], JSON_UNESCAPED_UNICODE);
        }

        if ($exception instanceof HttpExceptionInterface) {
            return response()->json([
                'error' => 'HTTP Error',
                'message' => $exception->getMessage(),
            ], $exception->getStatusCode(), [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json([
            'error' => 'Server Error',
            'message' => $exception->getMessage(),
        ], 500, [], JSON_UNESCAPED_UNICODE);
    }
}
