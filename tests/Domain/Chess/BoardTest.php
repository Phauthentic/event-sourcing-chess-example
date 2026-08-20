<?php

declare(strict_types=1);

namespace App\Tests\Domain\Chess;

use App\Domain\Chess\Board;
use App\Domain\Chess\Exception\NoPieceOnSquare;
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

        $this->assertEquals(32, $board->pieceCount());
        $this->assertEquals(16, $board->pieceCount(Side::WHITE));
        $this->assertEquals(16, $board->pieceCount(Side::BLACK));

        $pawn = $board->pieceAt(new Position('e2'));
        $this->assertNotNull($pawn);
        $this->assertEquals(Side::WHITE, $pawn->side);
        $this->assertEquals(PieceType::PAWN, $pawn->type);
    }

    public function testMove(): void
    {
        $board = new Board();

        $from = new Position('e2');
        $to = new Position('e4');

        $piece = $board->pieceAt($from);
        $this->assertInstanceOf(Piece::class, $piece);
        $this->assertEquals(Side::WHITE, $piece->side);
        $this->assertEquals(PieceType::PAWN, $piece->type);

        $board->move($from, $to);

        $this->assertNull($board->pieceAt($from));
        $this->assertSame($piece, $board->pieceAt($to));
    }

    public function testMoveFromEmptySquareThrows(): void
    {
        $board = Board::fromFen('4k3/8/8/8/8/8/8/4K3');

        $this->expectException(NoPieceOnSquare::class);

        $board->move(new Position('a1'), new Position('a2'));
    }

    public function testIsPathClear(): void
    {
        $board = new Board();

        // Blocked path - there is a pawn on a2 between a1 and a8
        $this->assertFalse($board->isPathClear(new Position('a1'), new Position('a8')));

        // Blocked path - there are pieces between a1 and h1
        $this->assertFalse($board->isPathClear(new Position('a1'), new Position('h1')));

        // Clear path - remove the pieces in between
        foreach (['b1', 'c1', 'd1', 'e1', 'f1', 'g1'] as $square) {
            $board->remove(new Position($square));
        }

        $this->assertTrue($board->isPathClear(new Position('a1'), new Position('h1')));
    }

    public function testKingPosition(): void
    {
        $board = new Board();

        $this->assertEquals('e1', $board->kingPosition(Side::WHITE)->position);
        $this->assertEquals('e8', $board->kingPosition(Side::BLACK)->position);
    }

    public function testCloneIsIndependent(): void
    {
        $board = new Board();
        $clonedBoard = clone $board;

        $this->assertNotNull($board->pieceAt(new Position('e1')));
        $this->assertNotNull($clonedBoard->pieceAt(new Position('e1')));
        $this->assertNotSame($board, $clonedBoard);

        // Move a piece on the cloned board
        $clonedBoard->move(new Position('e2'), new Position('e4'));

        // Original board is unchanged
        $this->assertNotNull($board->pieceAt(new Position('e2')));
        $this->assertNull($board->pieceAt(new Position('e4')));
    }

    public function testFenRoundTrip(): void
    {
        $this->assertEquals(Board::START_FEN, new Board()->toFen());

        $fen = 'r3k3/3p2P1/8/4P3/8/8/P7/4K2R';
        $this->assertEquals($fen, Board::fromFen($fen)->toFen());
    }

    public function testPlaceAndRemove(): void
    {
        $board = Board::fromFen('4k3/8/8/8/8/8/8/4K3');
        $position = new Position('d4');

        $this->assertNull($board->pieceAt($position));

        $board->place($position, new Piece(PieceType::QUEEN, Side::WHITE));
        $this->assertEquals(PieceType::QUEEN, $board->pieceAt($position)?->type);

        $board->remove($position);
        $this->assertNull($board->pieceAt($position));
    }
}
