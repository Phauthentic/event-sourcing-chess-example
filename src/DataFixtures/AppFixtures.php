<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        for ($i = 0; $i < 5; $i++) {
            $product = new User();
            $product->setEmail('user' . $i . '@test.local');
            $product->setPassword('password');
            $product->setUserName('user' . $i);
            $manager->persist($product);
        }

        $manager->flush();
    }
}
