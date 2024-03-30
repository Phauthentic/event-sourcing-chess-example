<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\SignUpTransfer;
use App\Entity\User;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class SignUpService
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator
    )
    {
    }

    public function signUp(SignUpTransfer $signUpTransfer): mixed
    {
        $user = new User();
        $user->setUsername($signUpTransfer->getUsername());
        $user->setEmail($signUpTransfer->getEmail());
        $user->setCreatedAt(new DateTimeImmutable());

        $errors = $this->validator->validate($user);
        if ($errors->count() > 0) {
            return $errors;
        }

        $hashedPassword = $this->passwordHasher->hashPassword(
            $user,
            $signUpTransfer->getPassword()
        );

        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return null;
    }
}
