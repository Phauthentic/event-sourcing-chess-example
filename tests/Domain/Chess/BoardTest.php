<?php

declare(strict_types=1);

namespace App\Tests\Domain\Chess;

use App\Domain\Chess\Board;
use App\Domain\Chess\Piece;
use App\Domain\Chess\PieceType;
use App\Domain\Chess\Position;
use App\Domain\Chess\Side;
use PHPUnit\Framework\TestCase;

class BoardTest extends TestCase
{
    public function testBoardInitialization(): void
    {
        $board = new Board();

        // Test that board has 32 pieces initially (16 white + 16 black)
        $this->assertEquals(32, $board->getNumberOfPieces());

        // Test that board has 16 white pieces
        $this->assertEquals(16, $board->getNumberOfPieces(Side::WHITE));

        // Test that board has 16 black pieces
        $this->assertEquals(16, $board->getNumberOfPieces(Side::BLACK));

        // Test that e2 has a white pawn
        $this->assertNotNull($board->fieldHasPawn(new Position('e2')));
        $pawn = $board->fieldHasPawn(new Position('e2'));
        $this->assertEquals(Side::WHITE, $pawn->side);
    }

    public function testMovePiece(): void
    {
        $board = new Board();

        $from = new Position('e2');
        $to = new Position('e4');

        // Get the piece at e2 (should be a white pawn)
        $piece = $board->getPiece($from);
        $this->assertInstanceOf(Piece::class, $piece);
        $this->assertEquals(Side::WHITE, $piece->side);
        $this->assertEquals(PieceType::PAWN, $piece->type);

        // Move the piece
        $board->movePiece($piece, $to);

        // Assert that the piece has moved to the new position
        $this->assertNull($board->fieldHasPawn($from));
        $this->assertNotNull($board->fieldHasPawn($to));
        $movedPiece = $board->fieldHasPawn($to);
        $this->assertEquals($piece, $movedPiece);
        $this->assertEquals($to, $movedPiece->position);
    }

    // Add more test cases for other methods in the Board class...
}
