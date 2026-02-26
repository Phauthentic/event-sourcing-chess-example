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

    public function testIsPathClear(): void
    {
        $board = new Board();

        // Test clear path - rook from a1 to a8 (but there's a pawn on a2, so it's blocked)
        $this->assertFalse($board->isPathClear(new Position('a1'), new Position('a8')));

        // Test blocked path - there are pieces between a1 and h1
        $this->assertFalse($board->isPathClear(new Position('a1'), new Position('h1')));

        // Test a clear path - remove pieces to make it clear
        $board->removePiece(new Position('b1'));
        $board->removePiece(new Position('c1'));
        $board->removePiece(new Position('d1'));
        $board->removePiece(new Position('e1'));
        $board->removePiece(new Position('f1'));
        $board->removePiece(new Position('g1'));

        $this->assertTrue($board->isPathClear(new Position('a1'), new Position('h1')));
    }

    public function testGetKingPosition(): void
    {
        $board = new Board();

        $whiteKingPos = $board->getKingPosition(Side::WHITE);
        $this->assertEquals('e1', $whiteKingPos->toString());

        $blackKingPos = $board->getKingPosition(Side::BLACK);
        $this->assertEquals('e8', $blackKingPos->toString());
    }

    public function testIsSquareAttackedBy(): void
    {
        $board = new Board();

        // Test knight attacks (knights can jump)
        $this->assertTrue($board->isSquareAttackedBy(new Position('a3'), Side::WHITE)); // attacked by b1 knight
        $this->assertTrue($board->isSquareAttackedBy(new Position('c3'), Side::WHITE)); // attacked by b1 knight

        // Test pawn attacks
        $this->assertTrue($board->isSquareAttackedBy(new Position('b3'), Side::WHITE)); // attacked by a2 pawn
        $this->assertTrue($board->isSquareAttackedBy(new Position('f3'), Side::WHITE)); // attacked by e2 pawn

        // Test that own pieces don't attack squares occupied by own pieces
        $this->assertFalse($board->isSquareAttackedBy(new Position('h2'), Side::WHITE)); // occupied by own pawn, not attacked by own pieces
    }

    public function testClone(): void
    {
        $board = new Board();
        $clonedBoard = $board->clone();

        // Original board should still have pieces
        $this->assertTrue($board->fieldHasPiece(new Position('e1')));

        // Cloned board should also have pieces
        $this->assertTrue($clonedBoard->fieldHasPiece(new Position('e1')));

        // But they should be different objects
        $this->assertNotSame($board, $clonedBoard);

        // Move a piece on the cloned board
        $piece = $clonedBoard->getPiece(new Position('e2'));
        $clonedBoard->movePiece($piece, new Position('e4'));

        // Original board should be unchanged
        $this->assertTrue($board->fieldHasPiece(new Position('e2')));
        $this->assertFalse($board->fieldHasPiece(new Position('e4')));
    }

    public function testGetAllPositions(): void
    {
        $board = new Board();
        $positions = $board->getAllPositions();

        $this->assertCount(64, $positions);

        // Check that all positions are valid Position objects
        foreach ($positions as $position) {
            $this->assertInstanceOf(Position::class, $position);
            $this->assertMatchesRegularExpression('/^[a-h][1-8]$/', $position->toString());
        }

        // Check specific positions
        $positionStrings = array_map(fn($pos) => $pos->toString(), $positions);
        $this->assertContains('a1', $positionStrings);
        $this->assertContains('h8', $positionStrings);
        $this->assertContains('e4', $positionStrings);
    }

    public function testRemovePiece(): void
    {
        $board = new Board();

        $position = new Position('e2');
        $this->assertTrue($board->fieldHasPiece($position));

        $board->removePiece($position);
        $this->assertFalse($board->fieldHasPiece($position));
    }
}