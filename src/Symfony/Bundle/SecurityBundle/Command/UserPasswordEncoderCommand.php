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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Helper\Table;

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
            ->setDescription('Encode a password.')
            ->addArgument('password', InputArgument::OPTIONAL, 'Enter a password')
            ->addArgument('user-class', InputArgument::OPTIONAL, 'Enter the user class configured to find the encoder you need.')
            ->addArgument('salt', InputArgument::OPTIONAL, 'Enter the salt you want to use to encode your password.')
            ->setHelp(<<<EOF

The <info>%command.name%</info> command allows to encode a password using encoders
that are configured in the application configuration file, under the <comment>security.encoders</comment>.

For instance, if you have the following configuration for your application:
<comment>
    security:
        encoders:
            Symfony\Component\Security\Core\User\User: plaintext
            AppBundle\Model\User: bcrypt
</comment>

According to the response you will give to the question "<question>Provide your configured user class</question>" your
password will be encoded the way it was configured.
    - If you answer "<comment>Symfony\Component\Security\Core\User\User</comment>", the password provided will be encoded
      with the <comment>plaintext</comment> encoder.
    - If you answer <comment>AppBundle\Model\User</comment>, the password provided will be encoded
      with the <comment>bcrypt</comment> encoder.

The command allows you to provide your own <comment>salt</comment>. If you don't provide any,
the command will take care about that for you.

You can also use the non interactive way by typing the following command:
    <info>php %command.full_name% [password] [user-class] [salt]</info>

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
        $salt = $input->getArgument('salt');
        $userClass = $input->getArgument('user-class');

        $helper = $this->getHelper('question');

        if (!$password) {
            $passwordQuestion = $this->createPasswordQuestion($input, $output);
            $password = $helper->ask($input, $output, $passwordQuestion);
        }

        if (!$salt) {
            $saltQuestion = $this->createSaltQuestion($input, $output);
            $salt = $helper->ask($input, $output, $saltQuestion);
        }

        $output->writeln("\n <comment>Encoders are configured by user type in the security.yml file.</comment>");

        if (!$userClass) {
            $userClassQuestion = $this->createUserClassQuestion($input, $output);
            $userClass = $helper->ask($input, $output, $userClassQuestion);
        }

        $encoder = $this->getContainer()->get('security.encoder_factory')->getEncoder($userClass);
        $encodedPassword = $encoder->encodePassword($password, $salt);

        $this->writeResult($output);

        $table = new Table($output);
        $table
            ->setHeaders(array('Key', 'Value'))
            ->addRow(array('Encoder used', get_class($encoder)))
            ->addRow(array('Encoded password', $encodedPassword))
        ;

        $table->render();
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
     * Create the question that asks for the salt to perform the encoding.
     * If there is no provided salt, a random one is automatically generated.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return Question
     */
    private function createSaltQuestion(InputInterface $input, OutputInterface $output)
    {
        $saltQuestion = new Question("\n > (Optional) <question>Provide a salt (press <enter> to generate one):</question> ");

        $container = $this->getContainer();
        $saltQuestion->setValidator(function ($value) use ($output, $container) {
            if ('' === trim($value)) {
                $value = base64_encode($container->get('security.secure_random')->nextBytes(30));

                $output->writeln("\n<comment>The salt has been generated: </comment>".$value);
                $output->writeln(sprintf("<comment>Make sure that your salt storage field fits this salt length: %s chars.</comment>\n", strlen($value)));
            }

            return $value;
        });

        return $saltQuestion;
    }

    /**
     * Create the question that asks for the configured user class.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return Question
     */
    private function createUserClassQuestion(InputInterface $input, OutputInterface $output)
    {
        $userClassQuestion = new Question(" > <question>Provide your configured user class:</question> ");
        $userClassQuestion->setAutocompleterValues(array('Symfony\Component\Security\Core\User\User'));

        $userClassQuestion->setValidator(function ($value) use ($output) {
            if ('' === trim($value)) {
                $value = 'Symfony\Component\Security\Core\User\User';
                $output->writeln("<info>You did not provide any user class.</info> <comment>The user class used is: Symfony\Component\Security\Core\User\User</comment> \n");
            }

            return $value;
        });

        return $userClassQuestion;
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
}
