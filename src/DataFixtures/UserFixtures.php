<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
//use Symfony\Component\String\Slugger\SluggerInterface;

class UserFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $passwordEncoder){}
    
    public function load(ObjectManager $manager, ): void
    {
        $admin = new User();
        $admin->setFirstname('Admin');
        $admin->setLastname('admin');
        $admin->setEmail('admin@myblog.org');
        $admin->setCity('Lyon');
        $admin->setZipcode('69001');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordEncoder->hashPassword($admin, 'adminuser'));
        
        $manager->persist($admin);

        $manager->flush();
    }
}
