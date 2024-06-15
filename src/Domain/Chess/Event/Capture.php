<?php

declare(strict_types=1);

namespace App\Domain\Chess\Event;

/**
 * When one piece captures another, a capture event is created.
 */
class Capture
{
    public function __construct(
        private string $capturingPiece,
        private string $capturedPiece,
        private string $capturingPosition,
        private string $capturedPosition
    )
    {
    }
}
