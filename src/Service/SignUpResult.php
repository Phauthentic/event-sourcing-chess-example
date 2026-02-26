<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Validator\ConstraintViolationList;

class SignUpResult extends OperationResult
{
    private ?ConstraintViolationList $constraintValidationList = null;

    public static function validationFailed(
        ConstraintViolationList $errors
    ): self
    {
        return new static(
            isSuccessful: false,
            errorCode: 1001,
            errorMessage: 'Validation failed',
        );
    }

    public static function success(): self
    {
        return new static(
            isSuccessful: true,
        );
    }

    public function getValidationErrors(): ConstraintViolationList
    {
        return $this->constraintValidationList;
    }
}
