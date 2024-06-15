<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 *
 */
class MoveDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'From can not be empty')]
        public string $from,
        #[Assert\NotBlank(message: 'To can not be empty.')]
        public string $to
    ) {
    }
}
