<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Phauthentic\ErrorResponse\ErrorResponse;
use Phauthentic\ErrorResponse\ErrorResponseInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class ErrorResponseService
{
    public function constraintViolationsToErrorResponse(
        ConstraintViolationListInterface $constraintViolationList
    ): Response {
        /** @var $error ConstraintViolationInterface */
        foreach ($constraintViolationList as $error) {
            $errorDetails[$error->getPropertyPath()][] = [
                'message' => $error->getMessage(),
                'code' => $error->getCode(),
                'parameters' => $error->getParameters()
            ];
        }

        $errorResponse = new ErrorResponse(
            status: 400,
            title: 'Validation failed',
            extensions: ['errors' => $errorDetails]
        );

        return $this->errorResponseToResponse($errorResponse);
    }

    public function errorResponseToResponse(ErrorResponseInterface $errorResponse): Response
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'application/problem+json');

        return $response
            ->setStatusCode($errorResponse->getStatus())
            ->setContent(json_encode($errorResponse->toArray(), JSON_THROW_ON_ERROR));
    }
}
