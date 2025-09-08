<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Crée un nouvel utilisateur'
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'Nom d\'utilisateur')
            ->addArgument('email', InputArgument::REQUIRED, 'Adresse email')
            ->addArgument('password', InputArgument::REQUIRED, 'Mot de passe (en clair, sera hashé)')
            ->addArgument('role', InputArgument::REQUIRED, 'Rôle (admin, consultation, cariste, prep)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io       = new SymfonyStyle($input, $output);
        $username = (string) $input->getArgument('username');
        $email    = (string) $input->getArgument('email');
        $password = (string) $input->getArgument('password');
        $roleArg  = strtoupper((string) $input->getArgument('role'));

        // Validation basique
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('Email invalide.');
            return Command::FAILURE;
        }
        if (strlen($username) < 2) {
            $io->error('Le nom d’utilisateur doit contenir au moins 2 caractères.');
            return Command::FAILURE;
        }
        if (strlen($password) < 4) {
            $io->warning('Mot de passe très court.'); // on laisse passer mais on avertit
        }

        // Normalisation & mapping du rôle
        $role = $this->normalizeRole($roleArg);
        if ($role === null) {
            $io->error('Rôle invalide. Utilisez : admin, consultation, cariste, prep');
            return Command::FAILURE;
        }

        // Unicité username / email
        $repo = $this->entityManager->getRepository(User::class);
        if ($repo->findOneBy(['username' => $username])) {
            $io->error(sprintf('Le nom d’utilisateur "%s" existe déjà.', $username));
            return Command::FAILURE;
        }
        if ($repo->findOneBy(['email' => $email])) {
            $io->error(sprintf('L’email "%s" est déjà utilisé.', $email));
            return Command::FAILURE;
        }

        try {
            $user = new User();
            $user->setUsername($username);
            $user->setEmail($email);

            // Hashage du mot de passe
            $hashed = $this->passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashed);

            // Attribution du rôle (ROLE_USER sera ajouté automatiquement par getRoles())
            $user->setRoles([$role]);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $io->success('Utilisateur créé avec succès ✅');
            $io->listing([
                'ID: ' . $user->getId(),
                'Username: ' . $user->getUsername(),
                'Email: ' . $user->getEmail(),
                'Rôles: ' . implode(', ', $user->getRoles()),
            ]);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Erreur lors de la création de l’utilisateur : ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Convertit des entrées libres vers un rôle Symfony valide.
     * Accepte des synonymes : admin|ROLE_ADMIN, consult|consultation|visu, cariste, prep|preparateur
     */
    private function normalizeRole(string $input): ?string
    {
        $in = strtoupper(trim($input));

        // Synonymes -> rôle canonique
        $map = [
            'ADMIN'                 => 'ROLE_ADMIN',
            'ROLE_ADMIN'            => 'ROLE_ADMIN',

            'CONSULTATION'          => 'ROLE_CONSULTATION',
            'CONSULT'               => 'ROLE_CONSULTATION',
            'VISU'                  => 'ROLE_CONSULTATION',
            'ROLE_CONSULTATION'     => 'ROLE_CONSULTATION',

            'CARISTE'               => 'ROLE_CARISTE',
            'ROLE_CARISTE'          => 'ROLE_CARISTE',

            'PREP'                  => 'ROLE_PREP',
            'PREPARATEUR'           => 'ROLE_PREP',
            'ROLE_PREP'             => 'ROLE_PREP',
        ];

        return $map[$in] ?? null;
    }
}
