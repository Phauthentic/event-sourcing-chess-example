<?php

declare(strict_types=1);

namespace App\Domain\Chess\Service;

use Exception;

class LongAlgebraicNotationParser
{
    private $board;
    private $currentPlayer;

    public function __construct()
    {
        $this->resetBoard();
        $this->currentPlayer = 'white';
    }

    public function parseMove(string $move)
    {
        $move = trim($move);

        // Check for castling
        if ($move === 'O-O') {
            return $this->handleCastling('kingside');
        } elseif ($move === 'O-O-O') {
            return $this->handleCastling('queenside');
        }

        // Regular move
        $pattern = '/^([KQRBNP])?([a-h])([1-8])-([a-h])([1-8])(=[QRBN])?(\+|#)?$/';
        if (!preg_match($pattern, $move, $matches)) {
            throw new Exception("Invalid move notation: $move");
        }

        $piece = $matches[1] ?: 'P';
        $fromFile = $matches[2];
        $fromRank = $matches[3];
        $toFile = $matches[4];
        $toRank = $matches[5];
        $promotion = isset($matches[6]) ? substr($matches[6], 1) : null;
        $check = isset($matches[7]) ? $matches[7] : null;

        $from = $fromFile . $fromRank;
        $to = $toFile . $toRank;

        $this->movePiece($piece, $from, $to, $promotion);

        $this->currentPlayer = ($this->currentPlayer === 'white') ? 'black' : 'white';

        return [
            'piece' => $piece,
            'from' => $from,
            'to' => $to,
            'promotion' => $promotion,
            'check' => $check
        ];
    }

    private function movePiece($piece, $from, $to, $promotion = null)
    {
        $this->board[$to] = $this->currentPlayer . $piece;
        unset($this->board[$from]);

        if ($promotion) {
            $this->board[$to] = $this->currentPlayer . $promotion;
        }
    }

    private function handleCastling($side)
    {
        $rank = ($this->currentPlayer === 'white') ? '1' : '8';
        if ($side === 'kingside') {
            $this->movePiece('K', "e$rank", "g$rank");
            $this->movePiece('R', "h$rank", "f$rank");
        } else {
            $this->movePiece('K', "e$rank", "c$rank");
            $this->movePiece('R', "a$rank", "d$rank");
        }

        $this->currentPlayer = ($this->currentPlayer === 'white') ? 'black' : 'white';

        return [
            'castling' => $side,
            'player' => $this->currentPlayer === 'white' ? 'black' : 'white'
        ];
    }

    public function getBoard()
    {
        return $this->board;
    }

    private function resetBoard()
    {
        $this->board = [
            'a8' => 'bR', 'b8' => 'bN', 'c8' => 'bB', 'd8' => 'bQ', 'e8' => 'bK', 'f8' => 'bB', 'g8' => 'bN', 'h8' => 'bR',
            'a7' => 'bP', 'b7' => 'bP', 'c7' => 'bP', 'd7' => 'bP', 'e7' => 'bP', 'f7' => 'bP', 'g7' => 'bP', 'h7' => 'bP',
            'a2' => 'wP', 'b2' => 'wP', 'c2' => 'wP', 'd2' => 'wP', 'e2' => 'wP', 'f2' => 'wP', 'g2' => 'wP', 'h2' => 'wP',
            'a1' => 'wR', 'b1' => 'wN', 'c1' => 'wB', 'd1' => 'wQ', 'e1' => 'wK', 'f1' => 'wB', 'g1' => 'wN', 'h1' => 'wR'
        ];
    }

    public function explainMove(string $move): string
    {
        $move = trim($move);

        // Check for castling
        if ($move === 'O-O') {
            return $this->explainCastling('kingside');
        } elseif ($move === 'O-O-O') {
            return $this->explainCastling('queenside');
        }

        // Regular move
        $pattern = '/^([KQRBNP])?([a-h])([1-8])-([a-h])([1-8])(=[QRBN])?(\+|#)?$/';
        if (!preg_match($pattern, $move, $matches)) {
            throw new Exception("Invalid move notation: $move");
        }

        $piece = $matches[1] ?: 'P';
        $fromFile = $matches[2];
        $fromRank = $matches[3];
        $toFile = $matches[4];
        $toRank = $matches[5];
        $promotion = isset($matches[6]) ? substr($matches[6], 1) : null;
        $check = isset($matches[7]) ? $matches[7] : null;

        $pieceNames = [
            'K' => 'King',
            'Q' => 'Queen',
            'R' => 'Rook',
            'B' => 'Bishop',
            'N' => 'Knight',
            'P' => 'Pawn'
        ];

        $explanation = "The " . $this->currentPlayer . " " . $pieceNames[$piece] . " moves from " . $fromFile . $fromRank . " to " . $toFile . $toRank;

        if ($promotion) {
            $explanation .= " and is promoted to a " . $pieceNames[$promotion];
        }

        if ($check === '+') {
            $explanation .= ", putting the opponent's King in check";
        } elseif ($check === '#') {
            $explanation .= ", checkmating the opponent's King";
        }

        return $explanation . ".";
    }

    private function explainCastling($side): string
    {
        $explanation = "The " . $this->currentPlayer . " King castles on the " . $side;

        if ($side === 'kingside') {
            $explanation .= ". The King moves from e1 to g1, and the Rook moves from h1 to f1";
        } else {
            $explanation .= ". The King moves from e1 to c1, and the Rook moves from a1 to d1";
        }

        return $explanation . ".";
    }
}
