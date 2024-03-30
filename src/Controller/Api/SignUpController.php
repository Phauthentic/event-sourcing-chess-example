<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\SignUpTransfer;
use App\Service\SignUpService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

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
        ): mixed
    {
        return $this->signUpService->signUp($signUpTransfer);

        return new Response();
    }
}
