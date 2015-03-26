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
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Encode a user's password.
 *
 * @author Sarah Khalil <mkhalil.sarah@gmail.com>
 */
class UserPasswordEncoderCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('security:encode-password')
            ->setDescription('Encodes a password.')
            ->addArgument('password', InputArgument::OPTIONAL, 'The plain password to encode.')
            ->addArgument('user-class', InputArgument::OPTIONAL, 'The User entity class path associated with the encoder used to encode the password.', 'Symfony\Component\Security\Core\User\User')
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

If you execute the command non-interactively, the default Symfony User class
is used and a random salt is generated to encode the password:

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
        $this->writeIntroduction($output);

        $password = $input->getArgument('password');
        $emptySalt = $input->getOption('empty-salt');
        $userClass = $input->getArgument('user-class');

        $helper = $this->getHelper('question');

        if (!$password) {
            if (!$input->isInteractive()) {
                throw new \Exception('The password must not be empty.');
            }
            $passwordQuestion = $this->createPasswordQuestion($input, $output);
            $password = $helper->ask($input, $output, $passwordQuestion);
        }

        $salt = null;

        if ($input->isInteractive() && !$emptySalt) {
            $emptySalt = true;
            if ($helper->ask($input, $output, $this->createSaltQuestion($output))) {
                $salt = $this->generateSalt();
                $emptySalt = false;
            }
        } elseif (!$emptySalt) {
            $salt = $this->generateSalt();
        }

        $encoder = $this->getContainer()->get('security.encoder_factory')->getEncoder($userClass);
        $encodedPassword = $encoder->encodePassword($password, $salt);

        $this->writeResult($output);

        $table = new Table($output);
        $table
            ->setHeaders(array('Key', 'Value'))
            ->addRow(array('Encoder used', get_class($encoder)))
            ->addRow(array('Encoded password', $encodedPassword));

        if ($emptySalt) {
            $table->render();
        } else {
            $table->addRow(array('Generated salt', $salt));
            $table->render();
            $output->writeln(sprintf("<comment>Make sure that your salt storage field fits this salt length: %s chars.</comment>\n", strlen($salt)));
        }
    }

    /**
     * Create the password question to ask the user for the password to be encoded.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return Question
     */
    private function createPasswordQuestion(InputInterface $input, OutputInterface $output)
    {
        $passwordQuestion = new Question("\n > <question>Type in your password to be encoded:</question> ");

        $passwordQuestion->setValidator(function ($value) {
            if ('' === trim($value)) {
                throw new \Exception('The password must not be empty.');
            }

            return $value;
        });
        $passwordQuestion->setHidden(true);
        $passwordQuestion->setMaxAttempts(20);

        return $passwordQuestion;
    }

    /**
     * Create the question that asks for the salt generation confirmation.
     *
     * @param OutputInterface $output
     *
     * @return ConfirmationQuestion
     */
    private function createSaltQuestion(OutputInterface $output)
    {
        $output->writeln(array(
            '<fg=yellow>! [NOTE] The command will take care of generating a salt for you.',
            '! Be aware that some encoders advise to let them generate their own salt.',
            '! If you\'re using the bcrypt encoder, please answer \'no\' to the question below.',
            '! Provide the \'empty-salt\' option in order to let the encoder handle the generation itself.</>'.PHP_EOL,
        ));

        return new ConfirmationQuestion('Confirm salt generation ? <info> (yes/no)</info> [<comment>yes</comment>]:', true);
    }

    private function writeIntroduction(OutputInterface $output)
    {
        $output->writeln(array(
            '',
            $this->getHelperSet()->get('formatter')->formatBlock(
                'Symfony Password Encoder Utility',
                'bg=blue;fg=white',
                true
            ),
            '',
        ));

        $output->writeln(array(
            '',
            'This command encodes any password you want according to the configuration you',
            'made in your configuration file containing the <comment>security.encoders</comment> key.',
            '',
        ));
    }

    private function writeResult(OutputInterface $output)
    {
        $output->writeln(array(
            '',
            $this->getHelperSet()->get('formatter')->formatBlock(
                '✔ Password encoding succeeded',
                'bg=green;fg=white',
                true
            ),
            '',
        ));
    }

    private function generateSalt()
    {
        return base64_encode($this->getContainer()->get('security.secure_random')->nextBytes(30));
    }
}
