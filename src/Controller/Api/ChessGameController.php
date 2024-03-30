<?php

declare(strict_types=1);

namespace App\Controller\Api;

use stdClass;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class ChessGameController
{
    public function __construct(
        private MessageBusInterface $messageBus
    ) {
    }

    #[Route('/api/chess-game/', methods: ['GET'])]
    public function createGame(): Response
    {
        $this->messageBus->dispatch(new stdClass());

        return new Response();
    }

    #[Route('/api/chess-game/{id}/move', methods: ['POST'])]
    public function move(): void
    {
        $this->messageBus->dispatch(new stdClass());
    }

    #[Route('/api/chess-game/{id}/board', methods: ['GET'])]
    public function board(): void
    {
        $this->messageBus->dispatch(new stdClass());
    }

    #[Route('/api/chess-game/{id}/check', methods: ['GET'])]
    public function check(): void
    {
        $this->messageBus->dispatch(new stdClass());
    }

    #[Route('/api/chess-game/{id}/offer-draw', methods: ['POST'])]
    public function offerDraw(): void
    {
        $this->messageBus->dispatch(new stdClass());
    }

    #[Route('/api/chess-game/{id}/accept-draw', methods: ['POST'])]
    public function acceptDraw(): void
    {
        $this->messageBus->dispatch(new stdClass());
    }
}
