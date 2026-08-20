<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\SignUpTransfer;
use App\Service\SignUpService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\ConstraintViolationListInterface;

#[AsController]
class SignUpController
{
    public function __construct(
        private SignUpService $signUpService,
    ) {
    }

    #[Route(
        '/api/sign-up',
        methods: ['POST']
    )]
    public function register(
        #[MapRequestPayload] SignUpTransfer $signUpTransfer
    ): JsonResponse {
        $violations = $this->signUpService->signUp($signUpTransfer);

        if ($violations instanceof ConstraintViolationListInterface && $violations->count() > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()][] = (string) $violation->getMessage();
            }

            return new JsonResponse(['errors' => $errors], 422);
        }

        return new JsonResponse(['success' => true], 201);
    }
}
