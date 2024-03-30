<?php

declare(strict_types=1);

namespace App\Domain\Chess\Command;

/**
 *
 */
class DeclareCheck
{
    public function __construct(
        public readonly string $playerWhiteId,
        public readonly string $playerBlackId,
    ) {
    }
}
