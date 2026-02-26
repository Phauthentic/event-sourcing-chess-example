<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\DoctrineEventEntity;
use Doctrine\ORM\EntityManagerInterface;
use Phauthentic\EventStore\Serializer\SerializerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:event-stream',
    description: 'Display event stream with full JSON payload (supports limit and offset)',
)]
class EventStreamCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Maximum number of events to show', 10)
            ->addOption('offset', 'o', InputOption::VALUE_REQUIRED, 'Number of events to skip', 0)
            ->addOption('aggregate-id', null, InputOption::VALUE_REQUIRED, 'Filter by aggregate ID')
            ->addOption('stream', null, InputOption::VALUE_REQUIRED, 'Filter by stream (e.g. App\\Domain\\Chess\\Game)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = (int) $input->getOption('limit');
        $offset = (int) $input->getOption('offset');
        $aggregateId = $input->getOption('aggregate-id');
        $stream = $input->getOption('stream');

        $repository = $this->entityManager->getRepository(DoctrineEventEntity::class);
        $qb = $repository->createQueryBuilder('e')
            ->orderBy('e.id', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        if ($aggregateId !== null && $aggregateId !== '') {
            $qb->andWhere('e.aggregateId = :aggregateId')
                ->setParameter('aggregateId', $aggregateId);
        }

        if ($stream !== null && $stream !== '') {
            $qb->andWhere('e.stream = :stream')
                ->setParameter('stream', $stream);
        }

        $entities = $qb->getQuery()->getResult();

        if (empty($entities)) {
            $io->writeln('[]');

            return Command::SUCCESS;
        }

        $events = [];
        foreach ($entities as $entity) {
            $payload = $this->serializer->unserialize($entity->getPayload());
            $events[] = [
                'id' => $entity->getId(),
                'stream' => $entity->getStream(),
                'aggregateId' => $entity->getAggregateId(),
                'version' => $entity->getVersion(),
                'event' => $entity->getEvent(),
                'createdAt' => $entity->getCreatedAt(),
                'payload' => $this->toArray($payload),
            ];
        }

        $io->writeln(json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return Command::SUCCESS;
    }

    private function toArray(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($v) => $this->toArray($v), $value);
        }

        if (is_object($value)) {
            $result = [];
            foreach ((array) $value as $k => $v) {
                $key = preg_replace('/^\x00[^\x00]*\x00/', '', (string) $k);
                $result[$key] = $this->toArray($v);
            }

            return $result;
        }

        return $value;
    }
}
