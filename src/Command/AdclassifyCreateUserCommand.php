<?php

namespace Adshares\Adclassify\Command;

use Adshares\Adclassify\Entity\ApiKey;
use Adshares\Adclassify\Entity\User;
use Adshares\Adclassify\Repository\UserRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class AdclassifyCreateUserCommand extends Command
{
    protected static $defaultName = 'app:user:create';

    private $userRepository;

    public function __construct(
        UserRepository $userRepository,
        string $name = null
    ) {
        parent::__construct($name);
        $this->userRepository = $userRepository;
    }

    protected function configure()
    {
        $this
            ->setDescription('Add a short description for your command')
            ->addArgument('email', InputArgument::OPTIONAL, 'User email address')
            ->addArgument('fullname', InputArgument::OPTIONAL, 'User full name')
            ->addArgument('password', InputArgument::OPTIONAL, 'User password')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Add ADMIN role')
            ->addOption('classifier', null, InputOption::VALUE_NONE, 'Add CLASSIFIER role');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getArgument('email') ?? $io->ask('Email address');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Invalid email address.');
        }

        $fullname = $input->getArgument('fullname') ?? $io->ask('Full name');
        if (empty($fullname)) {
            throw new \RuntimeException('Name cannot be empty.');
        }

        $password = str_replace(['+', '/', '='], ['x', 'y', ''], base64_encode(random_bytes(8)));
        $password = $input->getArgument('password') ?? $io->ask('Password', $password);
        if (empty($password)) {
            throw new \RuntimeException('Password cannot be empty.');
        }

        $role = 'CLIENT';
        if ($input->getOption('admin')) {
            $role = 'ADMIN';
        } elseif ($input->getOption('classifier')) {
            $role = 'CLASSIFIER';
        }

        $user = $this->userRepository->createUser($email, $fullname, $password, ['ROLE_' . $role]);
        $apiKey = $user->getApiKeys()->first();

        $io->success([
            sprintf('%s %s (%s) has been created', $role, $user->getFullName(), $user->getEmail()),
            sprintf('Password: %s', $password),
            sprintf('API key name: %s', $apiKey->getName()),
            sprintf('API key secret: %s', $apiKey->getSecret()),
        ]);
    }
}