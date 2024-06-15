<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 *
 */
class PlayerBlackDto
{

    public function __construct(
        #[Assert\NotBlank(message: 'Player name cannot be empty.')]
        public string $name,
    ) {
    }
}
