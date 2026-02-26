<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Chess\Exception\ChessDomainException;
use App\Service\ChessGameService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class ChessGameController
{
    public function __construct(
        private ChessGameService $chessGameService
    ) {
    }

    #[Route('/api/chess-game', methods: ['POST'])]
    public function createGame(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $playerOne = $data['playerOne'] ?? 'Player 1';
        $playerTwo = $data['playerTwo'] ?? 'Player 2';

        $gameId = $this->chessGameService->createGame($playerOne, $playerTwo);

        return new JsonResponse(['gameId' => $gameId], 201);
    }

    #[Route('/api/chess-game/{id}/move', methods: ['POST'])]
    public function move(string $id, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true) ?? [];
            $from = $data['from'] ?? null;
            $to = $data['to'] ?? null;

            if (!$from || !$to) {
                return new JsonResponse(
                    ['error' => 'Missing required fields: from, to'],
                    400
                );
            }

            $this->chessGameService->move($id, $from, $to);

            return new JsonResponse(['success' => true]);
        } catch (ChessDomainException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (NotFoundHttpException $e) {
            return new JsonResponse(['error' => 'Game not found'], 404);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/api/chess-game/{id}/board', methods: ['GET'])]
    public function board(string $id): JsonResponse
    {
        try {
            $state = $this->chessGameService->getBoardState($id);

            return new JsonResponse($state);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Game not found'], 404);
        }
    }

    #[Route('/api/chess-game/{id}/check', methods: ['POST'])]
    public function check(string $id): JsonResponse
    {
        try {
            $this->chessGameService->announceCheck($id);

            return new JsonResponse(['success' => true]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Game not found'], 404);
        }
    }

    #[Route('/api/chess-game/{id}/offer-draw', methods: ['POST'])]
    public function offerDraw(string $id): JsonResponse
    {
        try {
            $this->chessGameService->offerDraw($id);

            return new JsonResponse(['success' => true]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Game not found'], 404);
        }
    }

    #[Route('/api/chess-game/{id}/accept-draw', methods: ['POST'])]
    public function acceptDraw(string $id): JsonResponse
    {
        try {
            $this->chessGameService->acceptDraw($id);

            return new JsonResponse(['success' => true]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Game not found'], 404);
        }
    }
}
