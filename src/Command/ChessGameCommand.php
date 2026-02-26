<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Chess\Exception\ChessDomainException;
use App\Service\ChessBoardRenderer;
use App\Service\ChessGameService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'chess:game',
    description: 'Interactive chess game shell - play via console'
)]
class ChessGameCommand extends Command
{
    public function __construct(
        private ChessGameService $chessGameService,
        private ChessBoardRenderer $boardRenderer
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('game-id', 'g', InputOption::VALUE_REQUIRED, 'Load existing game by ID')
            ->setHelp('Play chess interactively. Enter moves as "from to" (e.g. e2 e4). Type "quit" to exit.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $gameId = $input->getOption('game-id');

        if (!$input->isInteractive()) {
            $gameId = $gameId ?? $this->chessGameService->createGame('Player 1', 'Player 2');
            $this->chessGameService->move($gameId, 'e2', 'e4');
            $state = $this->chessGameService->getBoardState($gameId);
            $output->writeln($this->boardRenderer->render($state['board']));
            $io->success('Demo move e2-e4 completed. Game ID: ' . $gameId);

            return Command::SUCCESS;
        }

        if ($gameId) {
            try {
                $this->chessGameService->getGame($gameId);
            } catch (\Throwable $e) {
                $io->error('Game not found: ' . $gameId);

                return Command::FAILURE;
            }
        } else {
            $gameId = $this->chessGameService->createGame('Player 1', 'Player 2');
            $io->success('New game created. Game ID: ' . $gameId);
        }

        $io->title('Chess Game');
        $io->text(['Enter moves as "from to" (e.g. e2 e4)', 'Commands: board, check, offer-draw, accept-draw, quit', '']);

        $questionHelper = $this->getHelper('question');

        while (true) {
            $state = $this->chessGameService->getBoardState($gameId);
            $io->section('Board');
            $output->writeln($this->boardRenderer->render($state['board']));
            $io->text(['Active player: ' . $state['activePlayer'], '']);

            $question = new Question(
                $state['activePlayer'] . ' to move (e.g. e2 e4): ',
                null
            );
            $question->setValidator(function (?string $value) {
                if ($value === null || $value === '') {
                    return null;
                }

                return trim($value);
            });

            $inputLine = $questionHelper->ask($input, $output, $question);

            if ($inputLine === null) {
                continue;
            }

            $inputLine = strtolower(trim($inputLine));

            if ($inputLine === 'quit' || $inputLine === 'q' || $inputLine === 'exit') {
                $io->success('Game saved. Resume with: php bin/console chess:game --game-id=' . $gameId);

                return Command::SUCCESS;
            }

            if ($inputLine === 'board') {
                continue;
            }

            if ($inputLine === 'check') {
                try {
                    $this->chessGameService->announceCheck($gameId);
                    $io->success('Check announced.');
                } catch (\Throwable $e) {
                    $io->error($e->getMessage());
                }
                continue;
            }

            if ($inputLine === 'offer-draw') {
                try {
                    $this->chessGameService->offerDraw($gameId);
                    $io->success('Draw offered.');
                } catch (\Throwable $e) {
                    $io->error($e->getMessage());
                }
                continue;
            }

            if ($inputLine === 'accept-draw') {
                try {
                    $this->chessGameService->acceptDraw($gameId);
                    $io->success('Draw accepted. Game over.');
                    break;
                } catch (\Throwable $e) {
                    $io->error($e->getMessage());
                }
                continue;
            }

            $parts = preg_split('/\s+/', $inputLine, 2);
            if (count($parts) !== 2) {
                $io->warning('Invalid input. Use "from to" (e.g. e2 e4)');
                continue;
            }

            [$from, $to] = $parts;

            try {
                $this->chessGameService->move($gameId, $from, $to);
                $io->success("Moved $from to $to");
            } catch (ChessDomainException $e) {
                $io->error($e->getMessage());
            } catch (\InvalidArgumentException $e) {
                $io->error($e->getMessage());
            }
        }

        return Command::SUCCESS;
    }

}
