<?php

declare(strict_types=1);

namespace App\DTO\Chess;

use Symfony\Component\Validator\Constraints as Assert;

/**
 *
 */
class NewChessGameDto
{

    public function __construct(
        #[Assert\NotBlank(message: 'White players name cannot be empty.')]
        public PlayerWhiteDto $white,
        #[Assert\NotBlank(message: 'Black players name cannot be empty.')]
        public PlayerBlackDto $black,
    ) {
    }
}
