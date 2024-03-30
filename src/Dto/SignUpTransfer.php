<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class SignUpTransfer
{
    public function __construct(
        //#[Assert\Length(min: 2, max: 32)]
        public string $username,

        //#[Assert\Email()]
        //#[Assert\NotBlank()]
        public string $email,

        //#[Assert\NotBlank()]
        //#[Assert\Length(min: 8, max: 64)]
        public string $password
    ) {
        $this->username = $username;
        $this->email = $email;
        $this->password = $password;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }
}
