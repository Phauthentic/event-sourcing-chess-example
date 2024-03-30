<?php

declare(strict_types=1);

namespace App\EventSourcing;

use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Notice that the message bus is another one than the default and should be used explicitly for domain events.
 */
class SymfonyMessengerEmitter
{
    public function __construct(
        private MessageBusInterface $domainEventMessageBus
    ) {
    }

    public function emit(object $event): void
    {
        $this->domainEventMessageBus->dispatch($event);
    }
}
