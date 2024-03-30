<?php

declare(strict_types=1);

namespace App\Domain\Chess;

/**
 *
 */
class BoardId
{
    public function __construct(
        public readonly string $id
    ) {
    }
}
