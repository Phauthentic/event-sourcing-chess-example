<?php

declare(strict_types=1);

namespace App\Tests\Domain\Chess;

use App\Domain\Chess\Board;
use App\Domain\Chess\BoardId;
use App\Domain\Chess\Piece;
use App\Domain\Chess\Player;
use App\Domain\Chess\Position;
use App\Domain\Chess\Side;
use PHPUnit\Framework\TestCase;

class BoardTest extends TestCase
{
    public function testGameStartedEventIsRecorded(): void
    {
        $boardId = new BoardId();
        $playerOne = new Player('Player One', Side::WHITE);
        $playerTwo = new Player('Player Two', Side::BLACK);

        $board = new Board($boardId, $playerOne, $playerTwo);

        $domainEvents = $board->getDomainEvents();

        $this->assertCount(1, $domainEvents);
        $this->assertInstanceOf(GameStarted::class, $domainEvents[0]);
        $this->assertEquals($boardId, $domainEvents[0]->getBoardId());
        $this->assertEquals($playerOne, $domainEvents[0]->getPlayerOne());
        $this->assertEquals($playerTwo, $domainEvents[0]->getPlayerTwo());
    }

    public function testMovePiece(): void
    {
        $boardId = new BoardId();
        $playerOne = new Player('Player One', Side::WHITE);
        $playerTwo = new Player('Player Two', Side::BLACK);

        $board = new Board($boardId, $playerOne, $playerTwo);

        $from = new Position('e2');
        $to = new Position('e4');

        $board->move($from, $to);

        // Assert that the piece has moved to the new position
        $this->assertNull($board->fieldHasPawn($from));
        $this->assertInstanceOf(Piece::class, $board->fieldHasPawn($to));
    }

    // Add more test cases for other methods in the Board class...
}
