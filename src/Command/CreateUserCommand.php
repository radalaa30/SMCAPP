<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'app:create-user',
    description: 'Crée un nouvel utilisateur'
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'Nom d\'utilisateur')
            ->addArgument('email', InputArgument::REQUIRED, 'Adresse email')
            ->addArgument('password', InputArgument::REQUIRED, 'Mot de passe')
            ->addArgument('role', InputArgument::REQUIRED, 'Rôle (admin, consultation, ou cariste)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        // Récupération des arguments
        $username = $input->getArgument('username');
        $email = $input->getArgument('email');
        $password = $input->getArgument('password');
        $role = strtoupper($input->getArgument('role'));

        // Validation du rôle
        $roleMap = [
            'ADMIN' => 'ROLE_ADMIN',
            'CONSULTATION' => 'ROLE_CONSULTATION',
            'CARISTE' => 'ROLE_CARISTE'
        ];

        if (!isset($roleMap[$role])) {
            $io->error('Rôle invalide. Utilisez : admin, consultation, ou cariste');
            return Command::FAILURE;
        }

        try {
            // Création de l'utilisateur
            $user = new User();
            $user->setUsername($username);
            $user->setEmail($email);
            
            // Hashage du mot de passe
            $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);
            
            // Attribution du rôle
            $user->setRoles([$roleMap[$role]]);

            // Sauvegarde en base de données
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $io->success(sprintf('Utilisateur "%s" créé avec succès avec le rôle "%s"', $username, $roleMap[$role]));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Une erreur est survenue lors de la création de l\'utilisateur : ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}