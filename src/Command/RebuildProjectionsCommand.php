<?php

declare(strict_types=1);

namespace App\Command;

use App\EventSourcing\EventStore\DoctrineEventStore;
use App\Projection\ChessGameProjector;
use Doctrine\ORM\EntityManagerInterface;
use Phauthentic\EventStore\EventInterface;
use Phauthentic\EventStore\Serializer\SerializerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:projections:rebuild',
    description: 'Rebuild read model projections from event store',
)]
class RebuildProjectionsCommand extends Command
{
    public function __construct(
        private DoctrineEventStore $eventStore,
        private ChessGameProjector $chessGameProjector,
        private SerializerInterface $serializer
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Rebuilding Projections');

        // Reset projections
        $io->section('Resetting projections...');
        $this->chessGameProjector->reset();
        $io->success('Projections reset');

        // Replay events from event store
        $io->section('Replaying events from event store...');

        $progressBar = $io->createProgressBar();
        $progressBar->start();

        $eventsProcessed = 0;

        // Note: This is a simplified implementation. In a production system,
        // you'd want to iterate through events more efficiently, possibly
        // in batches or with proper pagination.

        // Since DoctrineEventStore doesn't have a global stream method,
        // we'll iterate through all events. This is inefficient for large
        // event stores but works for demonstration.

        // For a proper implementation, you'd need to extend EventStoreInterface
        // with a method like getAllEvents() or getEventsByCriteria()

        // For now, we'll use a simplified approach by directly querying the entity
        $entityManager = $this->getEntityManagerFromEventStore();
        $eventEntities = $entityManager->createQuery(
            'SELECT e FROM App\Entity\DoctrineEventEntity e ORDER BY e.createdAt ASC'
        )->getResult();

        foreach ($eventEntities as $eventEntity) {
            $event = $this->recreateEventFromEntity($eventEntity);

            if ($this->chessGameProjector->supports($event)) {
                $this->chessGameProjector->project($event);
            }

            $eventsProcessed++;
            $progressBar->advance();
        }

        $progressBar->finish();
        $io->newLine(2);

        $io->success(sprintf('Projection rebuild completed. Processed %d events.', $eventsProcessed));

        return Command::SUCCESS;
    }

    /**
     * Get entity manager from event store.
     * This is a bit of a hack - in a real system, you'd inject the EM directly.
     */
    private function getEntityManagerFromEventStore(): EntityManagerInterface
    {
        // Access the private property via reflection (not ideal but works for demo)
        $reflection = new \ReflectionClass($this->eventStore);
        $property = $reflection->getProperty('entityManager');
        $property->setAccessible(true);
        return $property->getValue($this->eventStore);
    }

    /**
     * Recreate domain event from stored entity.
     */
    private function recreateEventFromEntity(object $eventEntity): object
    {
        $createdAt = \DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $eventEntity->getCreatedAt()
        );

        if ($createdAt === false) {
            $createdAt = new \DateTimeImmutable($eventEntity->getCreatedAt());
        }

        // Create the event object from payload
        $event = $this->serializer->unserialize($eventEntity->getPayload());

        return $event;
    }
}