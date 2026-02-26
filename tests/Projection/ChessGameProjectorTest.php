<?php

declare(strict_types=1);

namespace App\Tests\Projection;

use App\Domain\Chess\Event\GameStarted;
use App\Domain\Chess\Event\PieceMoved;
use App\Entity\ChessGameReadModel;
use App\Projection\ChessGameProjector;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ChessGameProjectorTest extends TestCase
{
    private ChessGameProjector $projector;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->projector = new ChessGameProjector($this->entityManager);
    }

    public function testSupportsGameStartedEvent(): void
    {
        $event = new GameStarted(
            gameId: 'game-123',
            playerOneId: 'Alice',
            playerTwoId: 'Bob',
            playerOneSide: 'white',
            playerTwoSide: 'black'
        );

        $this->assertTrue($this->projector->supports($event));
    }

    public function testSupportsPieceMovedEvent(): void
    {
        $event = new PieceMoved(
            pieceType: 'pawn',
            from: 'e2',
            to: 'e4'
        );

        $this->assertTrue($this->projector->supports($event));
    }

    public function testDoesNotSupportUnknownEvent(): void
    {
        $event = new \stdClass();
        $this->assertFalse($this->projector->supports($event));
    }

    public function testProjectsGameStartedEvent(): void
    {
        $event = new GameStarted(
            gameId: 'game-123',
            playerOneId: 'Alice',
            playerTwoId: 'Bob',
            playerOneSide: 'white',
            playerTwoSide: 'black'
        );

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($readModel) {
                return $readModel instanceof ChessGameReadModel
                    && $readModel->getGameId() === 'game-123'
                    && $readModel->getPlayerOneName() === 'Alice'
                    && $readModel->getPlayerTwoName() === 'Bob'
                    && $readModel->getActivePlayer() === 'Alice';
            }));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->projector->project($event);
    }

    public function testProjectsPieceMovedEvent(): void
    {
        $gameStartedEvent = new GameStarted(
            gameId: 'game-123',
            playerOneId: 'Alice',
            playerTwoId: 'Bob'
        );

        // First create the read model
        $readModel = new ChessGameReadModel('game-123');
        $readModel->setPlayerOneName('Alice');
        $readModel->setPlayerTwoName('Bob');
        $readModel->setActivePlayer('Alice');
        $readModel->initializeBoard();

        $this->entityManager
            ->expects($this->once())
            ->method('find')
            ->with(ChessGameReadModel::class, 'game-123')
            ->willReturn($readModel);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $moveEvent = new PieceMoved(
            gameId: 'game-123',
            pieceType: 'pawn',
            from: 'e2',
            to: 'e4'
        );

        $this->projector->project($moveEvent);

        // Verify the board was updated
        $board = $readModel->getBoard();
        $this->assertNull($board['e2']); // Piece moved from e2
        $this->assertNotNull($board['e4']); // Piece now at e4
        $this->assertEquals('Bob', $readModel->getActivePlayer()); // Player switched
    }

    public function testResetClearsReadModels(): void
    {
        $this->entityManager
            ->expects($this->once())
            ->method('createQuery')
            ->with('DELETE FROM App\Entity\ChessGameReadModel')
            ->willReturn($this->createMock(\Doctrine\ORM\Query::class));

        $this->projector->reset();
    }
}