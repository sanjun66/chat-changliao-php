<?php

namespace App\Exceptions;

use App\Tool\ResponseTrait;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    use ResponseTrait;
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
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
    public function render($request , Throwable $e)
    {
        $request->headers->set('Accept' , 'application/json');
        if ($e instanceof BadRequestHttpException) {
            $code = $e->getCode();
            return $this->responseError($e->getMessage(),$code ?: 422);
        }
        if ($e instanceof ValidationException) {
            return $this->responseError($e->validator->errors()->first());
        }
        if ($e instanceof NotFoundHttpException) {
            return $this->responseError(__("Route does not exist"));
        }
        return parent::render($request , $e);
    }
}
