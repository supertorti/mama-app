<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Task;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        # Admin user (PIN: 1234)
        $admin = new User();
        $admin->setName('Mama');
        $admin->hashPin('1234');
        $admin->setIsAdmin(true);
        $manager->persist($admin);

        # Child 1 (PIN: 0000)
        $child1 = new User();
        $child1->setName('Kind 1');
        $child1->hashPin('0000');
        $manager->persist($child1);

        # Child 2 (PIN: 1111)
        $child2 = new User();
        $child2->setName('Kind 2');
        $child2->hashPin('1111');
        $manager->persist($child2);

        # Test task 1 for Child 1
        $task1 = new Task();
        $task1->setUser($child1);
        $task1->setTitle('Zimmer aufräumen');
        $task1->setDescription('Das ganze Zimmer ordentlich aufräumen');
        $task1->setPoints(10);
        $task1->setDueDate(new \DateTimeImmutable('+1 day'));
        $manager->persist($task1);

        # Test task 2 for Child 1
        $task2 = new Task();
        $task2->setUser($child1);
        $task2->setTitle('Hausaufgaben machen');
        $task2->setPoints(5);
        $task2->setDueDate(new \DateTimeImmutable('+2 days'));
        $manager->persist($task2);

        # Test task 3 for Child 2
        $task3 = new Task();
        $task3->setUser($child2);
        $task3->setTitle('Müll rausbringen');
        $task3->setPoints(3);
        $task3->setDueDate(new \DateTimeImmutable('+1 day'));
        $manager->persist($task3);

        $manager->flush();
    }
}
