<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Chess\Board;
use App\Domain\Chess\Game;
use App\Domain\Chess\GameId;
use App\Domain\Chess\Player;
use App\Domain\Chess\Position;
use App\Domain\Chess\Side;
use Phauthentic\EventSourcing\Repository\EventSourcedRepositoryInterface;
use Ramsey\Uuid\Nonstandard\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'chess:game',
    description: 'Chess game shell'
)]
class ChessGameCommand extends Command
{
    protected static $defaultName = 'app:chessboard';

    public function __construct(
        private EventSourcedRepositoryInterface $eventSourcedRepository
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('')
            ->setHelp('');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $game = new Game(
            GameId::fromString(Uuid::uuid4()->toString()),
            new Player('player-1', Side::WHITE),
            new Player('player-2', Side::BLACK),
            new Board()
        );

        $game->move(
            Position::fromString('h2'),
            Position::fromString('h3'),
        );

        $output->writeln('Player finished the turn: ' . $game->getActivePlayer()->name);

        $this->eventSourcedRepository->persist($game);

        return Command::SUCCESS;
    }
}
