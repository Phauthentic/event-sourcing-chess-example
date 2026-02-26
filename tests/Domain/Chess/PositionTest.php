<?php

declare(strict_types=1);

namespace App\Tests\Domain\Chess;

use App\Domain\Chess\Position;
use PHPUnit\Framework\TestCase;

class PositionTest extends TestCase
{
    public function testPositionCreation(): void
    {
        $position = new Position('e4');
        $this->assertEquals('e4', $position->toString());
        $this->assertEquals('e4', (string) $position);
    }

    public function testFileAndRank(): void
    {
        $position = new Position('e4');
        $this->assertEquals('e', $position->file());
        $this->assertEquals(4, $position->rank());
    }

    public function testFileAndRankIndices(): void
    {
        $position = new Position('a1');
        $this->assertEquals(0, $position->fileIndex());
        $this->assertEquals(0, $position->rankIndex());

        $position = new Position('h8');
        $this->assertEquals(7, $position->fileIndex());
        $this->assertEquals(7, $position->rankIndex());

        $position = new Position('e4');
        $this->assertEquals(4, $position->fileIndex()); // e is 5th letter (0-indexed = 4)
        $this->assertEquals(3, $position->rankIndex()); // 4th rank (0-indexed = 3)
    }

    public function testDistanceTo(): void
    {
        $from = new Position('e4');
        $to = new Position('g6');

        [$fileDelta, $rankDelta] = $from->distanceTo($to);
        $this->assertEquals(2, $fileDelta); // g(6) - e(4) = 2
        $this->assertEquals(2, $rankDelta); // 6 - 4 = 2
    }

    public function testIsSameFile(): void
    {
        $pos1 = new Position('e4');
        $pos2 = new Position('e6');
        $pos3 = new Position('d4');

        $this->assertTrue($pos1->isSameFile($pos2));
        $this->assertFalse($pos1->isSameFile($pos3));
    }

    public function testIsSameRank(): void
    {
        $pos1 = new Position('e4');
        $pos2 = new Position('g4');
        $pos3 = new Position('e5');

        $this->assertTrue($pos1->isSameRank($pos2));
        $this->assertFalse($pos1->isSameRank($pos3));
    }

    public function testIsDiagonal(): void
    {
        $pos1 = new Position('e4');
        $pos2 = new Position('g6'); // diagonal
        $pos3 = new Position('f5'); // diagonal
        $pos4 = new Position('f4'); // same rank

        $this->assertTrue($pos1->isDiagonal($pos2));
        $this->assertTrue($pos1->isDiagonal($pos3));
        $this->assertFalse($pos1->isDiagonal($pos4));
    }

    public function testIsStraight(): void
    {
        $pos1 = new Position('e4');
        $pos2 = new Position('e6'); // same file
        $pos3 = new Position('g4'); // same rank
        $pos4 = new Position('f5'); // diagonal

        $this->assertTrue($pos1->isStraight($pos2));
        $this->assertTrue($pos1->isStraight($pos3));
        $this->assertFalse($pos1->isStraight($pos4));
    }

    public function testIsKnightMove(): void
    {
        $pos1 = new Position('e4');

        // Valid knight moves from e4
        $this->assertTrue($pos1->isKnightMove(new Position('f6'))); // +1 file, +2 rank
        $this->assertTrue($pos1->isKnightMove(new Position('g5'))); // +2 file, +1 rank
        $this->assertTrue($pos1->isKnightMove(new Position('g3'))); // +2 file, -1 rank
        $this->assertTrue($pos1->isKnightMove(new Position('f2'))); // +1 file, -2 rank
        $this->assertTrue($pos1->isKnightMove(new Position('d2'))); // -1 file, -2 rank
        $this->assertTrue($pos1->isKnightMove(new Position('c3'))); // -2 file, -1 rank
        $this->assertTrue($pos1->isKnightMove(new Position('c5'))); // -2 file, +1 rank
        $this->assertTrue($pos1->isKnightMove(new Position('d6'))); // -1 file, +2 rank

        // Invalid moves
        $this->assertFalse($pos1->isKnightMove(new Position('e5'))); // adjacent
        $this->assertFalse($pos1->isKnightMove(new Position('g6'))); // 2+2
        $this->assertFalse($pos1->isKnightMove(new Position('f7'))); // 1+3
    }

    public function testInvalidPosition(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Position('i9');
    }

    public function testFromString(): void
    {
        $position = Position::fromString('e4');
        $this->assertEquals('e4', $position->toString());
    }
}