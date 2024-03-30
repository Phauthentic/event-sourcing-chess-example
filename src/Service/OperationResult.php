<?php

declare(strict_types=1);

namespace App\Service;

class OperationResult
{
    public function __construct(
        protected bool $isSuccessful,
        protected int $errorCode = 0,
        protected string $errorMessage = '',
    )
    {
    }

    public static function failed(
        string $errorMessage,
        int $errorCode = 0
    ): self
    {
        return new static(
            isSuccessful: false,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
        );
    }

    public static function success(): self
    {
        return new static(
            isSuccessful: true,
        );
    }

    public function wasSuccessful(): bool
    {
        return $this->isSuccessful;
    }

    public function hasFailed(): bool
    {
        return !$this->isSuccessful;
    }

    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }
}