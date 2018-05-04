<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\Encoder\SelfSaltingEncoderInterface;
use Symfony\Component\Security\Core\User\User;

/**
 * Encode a user's password.
 *
 * @author Sarah Khalil <mkhalil.sarah@gmail.com>
 *
 * @final since version 3.4
 */
class UserPasswordEncoderCommand extends ContainerAwareCommand
{
    protected static $defaultName = 'security:encode-password';

    private $encoderFactory;
    private $userClasses;

    public function __construct(EncoderFactoryInterface $encoderFactory = null, array $userClasses = array())
    {
        if (null === $encoderFactory) {
            @trigger_error(sprintf('Passing null as the first argument of "%s" is deprecated since Symfony 3.3 and will be removed in 4.0. If the command was registered by convention, make it a service instead.', __METHOD__), E_USER_DEPRECATED);
        }

        $this->encoderFactory = $encoderFactory;
        $this->userClasses = $userClasses;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('Encodes a password.')
            ->addArgument('password', InputArgument::OPTIONAL, 'The plain password to encode.')
            ->addArgument('user-class', InputArgument::OPTIONAL, 'The User entity class path associated with the encoder used to encode the password.')
            ->addOption('empty-salt', null, InputOption::VALUE_NONE, 'Do not generate a salt or let the encoder generate one.')
            ->setHelp(<<<EOF

The <info>%command.name%</info> command encodes passwords according to your
security configuration. This command is mainly used to generate passwords for
the <comment>in_memory</comment> user provider type and for changing passwords
in the database while developing the application.

Suppose that you have the following security configuration in your application:

<comment>
# app/config/security.yml
security:
    encoders:
        Symfony\Component\Security\Core\User\User: plaintext
        AppBundle\Entity\User: bcrypt
</comment>

If you execute the command non-interactively, the first available configured
user class under the <comment>security.encoders</comment> key is used and a random salt is
generated to encode the password:

  <info>php %command.full_name% --no-interaction [password]</info>

Pass the full user class path as the second argument to encode passwords for
your own entities:

  <info>php %command.full_name% --no-interaction [password] AppBundle\Entity\User</info>

Executing the command interactively allows you to generate a random salt for
encoding the password:

  <info>php %command.full_name% [password] AppBundle\Entity\User</info>

In case your encoder doesn't require a salt, add the <comment>empty-salt</comment> option:

  <info>php %command.full_name% --empty-salt [password] AppBundle\Entity\User</info>

EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $errorIo = $output instanceof ConsoleOutputInterface ? new SymfonyStyle($input, $output->getErrorOutput()) : $io;

        $input->isInteractive() ? $errorIo->title('Symfony Password Encoder Utility') : $errorIo->newLine();

        $password = $input->getArgument('password');
        $userClass = $this->getUserClass($input, $io);
        $emptySalt = $input->getOption('empty-salt');

        $encoderFactory = $this->encoderFactory ?: $this->getContainer()->get('security.encoder_factory');
        $encoder = $encoderFactory->getEncoder($userClass);
        $saltlessWithoutEmptySalt = !$emptySalt && $encoder instanceof SelfSaltingEncoderInterface;

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

            $errorIo->note('The command will take care of generating a salt for you. Be aware that some encoders advise to let them generate their own salt. If you\'re using one of those encoders, please answer \'no\' to the question below. '.PHP_EOL.'Provide the \'empty-salt\' option in order to let the encoder handle the generation itself.');

            if ($errorIo->confirm('Confirm salt generation ?')) {
                $salt = $this->generateSalt();
                $emptySalt = false;
            }
        } elseif (!$emptySalt) {
            $salt = $this->generateSalt();
        }

        $encodedPassword = $encoder->encodePassword($password, $salt);

        $rows = array(
            array('Encoder used', get_class($encoder)),
            array('Encoded password', $encodedPassword),
        );
        if (!$emptySalt) {
            $rows[] = array('Generated salt', $salt);
        }
        $io->table(array('Key', 'Value'), $rows);

        if (!$emptySalt) {
            $errorIo->note(sprintf('Make sure that your salt storage field fits the salt length: %s chars', strlen($salt)));
        } elseif ($saltlessWithoutEmptySalt) {
            $errorIo->note('Self-salting encoder used: the encoder generated its own built-in salt.');
        }

        $errorIo->success('Password encoding succeeded');
    }

    /**
     * Create the password question to ask the user for the password to be encoded.
     *
     * @return Question
     */
    private function createPasswordQuestion()
    {
        $passwordQuestion = new Question('Type in your password to be encoded');

        return $passwordQuestion->setValidator(function ($value) {
            if ('' === trim($value)) {
                throw new InvalidArgumentException('The password must not be empty.');
            }

            return $value;
        })->setHidden(true)->setMaxAttempts(20);
    }

    private function generateSalt()
    {
        return base64_encode(random_bytes(30));
    }

    private function getUserClass(InputInterface $input, SymfonyStyle $io)
    {
        if (null !== $userClass = $input->getArgument('user-class')) {
            return $userClass;
        }

        if (empty($this->userClasses)) {
            if (null === $this->encoderFactory) {
                // BC to be removed and simply keep the exception whenever there is no configured user classes in 4.0
                return User::class;
            }

            throw new RuntimeException('There are no configured encoders for the "security" extension.');
        }

        if (!$input->isInteractive() || 1 === count($this->userClasses)) {
            return reset($this->userClasses);
        }

        $userClasses = $this->userClasses;
        natcasesort($userClasses);
        $userClasses = array_values($userClasses);

        return $io->choice('For which user class would you like to encode a password?', $userClasses, reset($userClasses));
    }
}
