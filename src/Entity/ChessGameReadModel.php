<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Read model for chess game state, maintained by projections.
 */
#[ORM\Entity]
#[ORM\Table(name: 'chess_game_read_model')]
class ChessGameReadModel
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $gameId;

    #[ORM\Column(type: 'json')]
    private array $board = [];

    #[ORM\Column(type: 'string', length: 255)]
    private string $activePlayer;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isInCheck = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $drawOffered = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isFinished = false;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $winner = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $playerOneName;

    #[ORM\Column(type: 'string', length: 255)]
    private string $playerTwoName;

    public function __construct(string $gameId)
    {
        $this->gameId = $gameId;
    }

    public function getGameId(): string
    {
        return $this->gameId;
    }

    public function getBoard(): array
    {
        return $this->board;
    }

    public function setBoard(array $board): void
    {
        $this->board = $board;
    }

    public function getActivePlayer(): string
    {
        return $this->activePlayer;
    }

    public function setActivePlayer(string $activePlayer): void
    {
        $this->activePlayer = $activePlayer;
    }

    public function isInCheck(): bool
    {
        return $this->isInCheck;
    }

    public function setIsInCheck(bool $isInCheck): void
    {
        $this->isInCheck = $isInCheck;
    }

    public function isDrawOffered(): bool
    {
        return $this->drawOffered;
    }

    public function setDrawOffered(bool $drawOffered): void
    {
        $this->drawOffered = $drawOffered;
    }

    public function isFinished(): bool
    {
        return $this->isFinished;
    }

    public function setIsFinished(bool $isFinished): void
    {
        $this->isFinished = $isFinished;
    }

    public function getWinner(): ?string
    {
        return $this->winner;
    }

    public function setWinner(?string $winner): void
    {
        $this->winner = $winner;
    }

    public function getPlayerOneName(): string
    {
        return $this->playerOneName;
    }

    public function setPlayerOneName(string $playerOneName): void
    {
        $this->playerOneName = $playerOneName;
    }

    public function getPlayerTwoName(): string
    {
        return $this->playerTwoName;
    }

    public function setPlayerTwoName(string $playerTwoName): void
    {
        $this->playerTwoName = $playerTwoName;
    }

    /**
     * Initialize the board with starting positions.
     */
    public function initializeBoard(): void
    {
        // Initialize empty board
        $files = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'];
        $board = [];
        for ($rank = 8; $rank >= 1; $rank--) {
            foreach ($files as $file) {
                $pos = $file . $rank;
                $board[$pos] = null;
            }
        }

        // Place pieces in starting positions
        $startingPositions = [
            // White pieces
            'a1' => ['type' => 'rook', 'side' => 'white', 'symbol' => '♖'],
            'b1' => ['type' => 'knight', 'side' => 'white', 'symbol' => '♘'],
            'c1' => ['type' => 'bishop', 'side' => 'white', 'symbol' => '♗'],
            'd1' => ['type' => 'queen', 'side' => 'white', 'symbol' => '♕'],
            'e1' => ['type' => 'king', 'side' => 'white', 'symbol' => '♔'],
            'f1' => ['type' => 'bishop', 'side' => 'white', 'symbol' => '♗'],
            'g1' => ['type' => 'knight', 'side' => 'white', 'symbol' => '♘'],
            'h1' => ['type' => 'rook', 'side' => 'white', 'symbol' => '♖'],
            'a2' => ['type' => 'pawn', 'side' => 'white', 'symbol' => '♙'],
            'b2' => ['type' => 'pawn', 'side' => 'white', 'symbol' => '♙'],
            'c2' => ['type' => 'pawn', 'side' => 'white', 'symbol' => '♙'],
            'd2' => ['type' => 'pawn', 'side' => 'white', 'symbol' => '♙'],
            'e2' => ['type' => 'pawn', 'side' => 'white', 'symbol' => '♙'],
            'f2' => ['type' => 'pawn', 'side' => 'white', 'symbol' => '♙'],
            'g2' => ['type' => 'pawn', 'side' => 'white', 'symbol' => '♙'],
            'h2' => ['type' => 'pawn', 'side' => 'white', 'symbol' => '♙'],

            // Black pieces
            'a8' => ['type' => 'rook', 'side' => 'black', 'symbol' => '♜'],
            'b8' => ['type' => 'knight', 'side' => 'black', 'symbol' => '♞'],
            'c8' => ['type' => 'bishop', 'side' => 'black', 'symbol' => '♝'],
            'd8' => ['type' => 'queen', 'side' => 'black', 'symbol' => '♛'],
            'e8' => ['type' => 'king', 'side' => 'black', 'symbol' => '♚'],
            'f8' => ['type' => 'bishop', 'side' => 'black', 'symbol' => '♝'],
            'g8' => ['type' => 'knight', 'side' => 'black', 'symbol' => '♞'],
            'h8' => ['type' => 'rook', 'side' => 'black', 'symbol' => '♜'],
            'a7' => ['type' => 'pawn', 'side' => 'black', 'symbol' => '♟'],
            'b7' => ['type' => 'pawn', 'side' => 'black', 'symbol' => '♟'],
            'c7' => ['type' => 'pawn', 'side' => 'black', 'symbol' => '♟'],
            'd7' => ['type' => 'pawn', 'side' => 'black', 'symbol' => '♟'],
            'e7' => ['type' => 'pawn', 'side' => 'black', 'symbol' => '♟'],
            'f7' => ['type' => 'pawn', 'side' => 'black', 'symbol' => '♟'],
            'g7' => ['type' => 'pawn', 'side' => 'black', 'symbol' => '♟'],
            'h7' => ['type' => 'pawn', 'side' => 'black', 'symbol' => '♟'],
        ];

        foreach ($startingPositions as $position => $piece) {
            $board[$position] = $piece;
        }

        $this->board = $board;
    }
}