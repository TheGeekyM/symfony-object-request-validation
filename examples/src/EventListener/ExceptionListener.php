<?php

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Fesor\RequestObject\InvalidRequestPayloadException;
use Symfony\Component\Validator\Exception\ValidatorException;

class ExceptionListener
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * ExceptionListener constructor.
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param ExceptionEvent $event
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        $response = '';
        $throwable = $event->getThrowable();
        $request = $event->getRequest();

        if (in_array('application/json', $request->getAcceptableContentTypes(), true)) {
            if ($throwable instanceof ValidatorException) {
                $response = $this->createApiWithValidationResponse($throwable->getErrors());
            } else {
                $response = $this->createApiResponse($throwable);
            }
        }

        $event->setResponse($response);

        $this->log($event->getThrowable());
    }

    private function log(\Throwable $throwable)
    {
        $log = [
            'code' => $throwable->getCode(),
            'message' => $throwable->getMessage(),
            'called' => [
                'file' => $throwable->getTrace()[0]['file'],
                'line' => $throwable->getTrace()[0]['line'],
            ],
            'occurred' => [
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
            ],
        ];

        if ($throwable->getPrevious() instanceof \Throwable) {
            $log += [
                'previous' => [
                    'message' => $throwable->getPrevious()->getMessage(),
                    'exception' => get_class($throwable->getPrevious()),
                    'file' => $throwable->getPrevious()->getFile(),
                    'line' => $throwable->getPrevious()->getLine(),
                ],
            ];
        }

        $this->logger->error(json_encode($log));
    }

    /**
     * Creates the ApiResponse from any Exception.
     *
     * @param \Throwable $throwable
     *
     * @return Response
     */
    private function createApiResponse(\Throwable $throwable): Response
    {
        $statusCode = $throwable->getCode() ?: Response::HTTP_INTERNAL_SERVER_ERROR;

        return new JsonResponse([
            'message' => $throwable->getMessage(),
        ], $statusCode);
    }

    /**
     * @param ConstraintViolationListInterface $errors
     *
     * @return JsonResponse|Response
     */
    public function createApiWithValidationResponse(ConstraintViolationListInterface $errors)
    {
        return new JsonResponse([
            'message' => 'Invalid Data',
            'errors' => array_map(static function (ConstraintViolation $violation) {
                return [
                    $violation->getPropertyPath() => $violation->getMessage(),
                ];
            }, iterator_to_array($errors))
        ], 422);
    }
}
