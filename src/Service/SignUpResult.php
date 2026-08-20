<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Validator\ConstraintViolationList;

class SignUpResult extends OperationResult
{
    private ?ConstraintViolationList $constraintValidationList = null;

    public static function validationFailed(
        ConstraintViolationList $errors
    ): self {
        $result = new static(
            isSuccessful: false,
            errorCode: 1001,
            errorMessage: 'Validation failed',
        );
        $result->constraintValidationList = $errors;

        return $result;
    }

    public static function success(): self
    {
        return new static(
            isSuccessful: true,
        );
    }

    public function getValidationErrors(): ?ConstraintViolationList
    {
        return $this->constraintValidationList;
    }
}
