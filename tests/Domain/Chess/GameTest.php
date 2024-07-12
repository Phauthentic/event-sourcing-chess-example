<?php

declare(strict_types=1);

namespace App\Tests\Domain\Chess;

use Phauthentic\EventSourcing\Repository\AggregateExtractor\AttributeBasedExtractor;
use Phauthentic\EventSourcing\Repository\AggregateFactory\ReflectionFactory;
use Phauthentic\EventSourcing\Repository\EventSourcedRepository;
use Phauthentic\EventSourcing\Repository\EventSourcedRepositoryInterface;
use Phauthentic\EventStore\EventFactory;
use Phauthentic\EventStore\InMemoryEventStore;
use Phauthentic\SnapshotStore\SnapshotFactory;
use Phauthentic\SnapshotStore\Store\InMemorySnapshotStore;
use PHPUnit\Framework\TestCase;
use App\Domain\Chess\{Game, GameId, Player, Side};

/**
 *
 */
final class GameTest extends TestCase
{
    protected function createRepository(): EventSourcedRepositoryInterface
    {
        return new EventSourcedRepository(
            eventStore: new InMemoryEventStore(),
            aggregateExtractor: new AttributeBasedExtractor(),
            aggregateFactory: new ReflectionFactory(),
            eventFactory: new EventFactory(),
            snapshotStore: new InMemorySnapshotStore(),
            snapshotFactory: new SnapshotFactory()
        );
    }

    public function testCreateGame(): void
    {
        $repository = $this->createRepository();

        $gameId = GameId::fromString('9ac26477-e806-42cf-978a-c4234245f531');
        $white = new Player('Alice', Side::WHITE);
        $black = new Player('Bob', Side::BLACK);

        $game = Game::create($gameId, $white, $black);

        $repository->persist($game);

        $game = $repository->restore((string) $gameId, Game::class);

        //dd($game->toArray());
    }
}
