<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PasswordHasher\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\LegacyPasswordHasherInterface;

/**
 * Hashes a user's password.
 *
 * @author Sarah Khalil <mkhalil.sarah@gmail.com>
 * @author Robin Chalas <robin.chalas@gmail.com>
 *
 * @final
 */
#[AsCommand(name: 'security:hash-password', description: 'Hash a user password')]
class UserPasswordHashCommand extends Command
{
    public function __construct(
        private PasswordHasherFactoryInterface $hasherFactory,
        private array $userClasses = [],
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('password', InputArgument::OPTIONAL, 'The plain password to hash.')
            ->addArgument('user-class', InputArgument::OPTIONAL, 'The User entity class path associated with the hasher used to hash the password.')
            ->addOption('empty-salt', null, InputOption::VALUE_NONE, 'Do not generate a salt or let the hasher generate one.')
            ->setHelp(<<<EOF

                The <info>%command.name%</info> command hashes passwords according to your
                security configuration. This command is mainly used to generate passwords for
                the <comment>in_memory</comment> user provider type and for changing passwords
                in the database while developing the application.

                Suppose that you have the following security configuration in your application:

                <comment>
                # config/packages/security.yml
                security:
                    password_hashers:
                        Symfony\Component\Security\Core\User\InMemoryUser: plaintext
                        App\Entity\User: auto
                </comment>

                Executing the command interactively prompts for the password and uses
                the first available configured user class under the
                <comment>security.password_hashers</comment> key:

                  <info>php %command.full_name%</info>

                Pass the full user class path as the second argument to hash passwords for
                your own entities (pass <comment>''</comment> as the first argument to keep the interactive prompt):

                  <info>php %command.full_name% '' 'App\Entity\User'</info>

                Passing the password on the command line is supported for non-interactive
                use, but exposes the plaintext to shell history and the process list
                (<comment>ps</comment>, <comment>/proc/&lt;pid&gt;/cmdline</comment>, container audit logs); prefer the
                interactive form when a terminal is available:

                  <info>php %command.full_name% --no-interaction [password] 'App\Entity\User'</info>

                Read the password from standard input by passing <comment>-</comment> as the password
                argument; this avoids exposing it to shell history or the process list:

                  <info>echo \$PASSWORD | php %command.full_name% --no-interaction -</info>

                In case your hasher doesn't require a salt, add the <comment>empty-salt</comment> option:

                  <info>php %command.full_name% --empty-salt</info>

                EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $errorIo = $output instanceof ConsoleOutputInterface ? new SymfonyStyle($input, $output->getErrorOutput()) : $io;

        $input->isInteractive() ? $errorIo->title('Symfony Password Hash Utility') : $errorIo->newLine();

        $password = $input->getArgument('password');

        if ('-' === $password) {
            $stream = $input instanceof StreamableInputInterface ? $input->getStream() : null;
            $password = rtrim(fgets($stream ?? \STDIN) ?: '', "\r\n");
        } elseif ($password && $input->isInteractive()) {
            $errorIo->warning('Passing the password as a command argument exposes it to shell history and the process list (ps, /proc/<pid>/cmdline, container audit logs); prefer the interactive prompt or pass "-" to read it from stdin.');
        }

        $userClass = $this->getUserClass($input, $io);
        $emptySalt = $input->getOption('empty-salt');

        $hasher = $this->hasherFactory->getPasswordHasher($userClass);
        $saltlessWithoutEmptySalt = !$emptySalt && !$hasher instanceof LegacyPasswordHasherInterface;

        if ($saltlessWithoutEmptySalt) {
            $emptySalt = true;
        }

        if (!$password) {
            if (!$input->isInteractive()) {
                $errorIo->error('The password must not be empty.');

                return 1;
            }
            $passwordQuestion = $this->createPasswordQuestion();
            $password = $errorIo->askQuestion($passwordQuestion);
        }

        $salt = null;

        if ($input->isInteractive() && !$emptySalt) {
            $emptySalt = true;

            $errorIo->note('The command will take care of generating a salt for you. Be aware that some hashers advise to let them generate their own salt. If you\'re using one of those hashers, please answer \'no\' to the question below. '.\PHP_EOL.'Provide the \'empty-salt\' option in order to let the hasher handle the generation itself.');

            if ($errorIo->confirm('Confirm salt generation ?')) {
                $salt = $this->generateSalt();
                $emptySalt = false;
            }
        } elseif (!$emptySalt) {
            $salt = $this->generateSalt();
        }

        $hashedPassword = $hasher->hash($password, $salt);

        $rows = [
            ['Hasher used', $hasher::class],
            ['Password hash', $hashedPassword],
        ];
        if (!$emptySalt) {
            $rows[] = ['Generated salt', $salt];
        }
        $io->table(['Key', 'Value'], $rows);

        if (!$emptySalt) {
            $errorIo->note(\sprintf('Make sure that your salt storage field fits the salt length: %s chars', \strlen($salt)));
        } elseif ($saltlessWithoutEmptySalt) {
            $errorIo->note('Self-salting hasher used: the hasher generated its own built-in salt.');
        }

        $errorIo->success('Password hashing succeeded');

        return 0;
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('user-class')) {
            $suggestions->suggestValues($this->userClasses);
        }
    }

    /**
     * Create the password question to ask the user for the password to be hashed.
     */
    private function createPasswordQuestion(): Question
    {
        $passwordQuestion = new Question('Type in your password to be hashed');

        return $passwordQuestion->setValidator(static function ($value) {
            if ('' === trim($value)) {
                throw new InvalidArgumentException('The password must not be empty.');
            }

            return $value;
        })->setHidden(true)->setMaxAttempts(20);
    }

    private function generateSalt(): string
    {
        return base64_encode(random_bytes(30));
    }

    private function getUserClass(InputInterface $input, SymfonyStyle $io): string
    {
        if (null !== $userClass = $input->getArgument('user-class')) {
            return $userClass;
        }

        if (!$this->userClasses) {
            throw new RuntimeException('There are no configured password hashers for the "security" extension.');
        }

        if (!$input->isInteractive() || 1 === \count($this->userClasses)) {
            return reset($this->userClasses);
        }

        $userClasses = $this->userClasses;
        natcasesort($userClasses);
        $userClasses = array_values($userClasses);

        return $io->choice('For which user class would you like to hash a password?', $userClasses, reset($userClasses));
    }
}
