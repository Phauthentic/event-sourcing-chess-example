<?php

declare(strict_types=1);

namespace App\Tests\Domain\Chess;

use App\Domain\Chess\Position;
use PHPUnit\Framework\TestCase;

class PositionTest extends TestCase
{
    public function testValidPosition(): void
    {
        $position = new Position('a1');
        $this->assertEquals('a1', $position->toString());

        $position = new Position('d8');
        $this->assertEquals('d8', $position->toString());
    }

    public function testInvalidPosition(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Position('z9');
    }
}
