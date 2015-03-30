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
            ->addArgument('password', InputArgument::OPTIONAL, 'The raw password to encode.')
            ->addOption('user-class', null, InputOption::VALUE_REQUIRED, 'The user class to retrieve the configured password encoder.')
            ->addOption('salt', null, InputOption::VALUE_REQUIRED, 'The salt to use to encode the raw password.')
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

You can also use the non interactive way:
    - the very simple way is to simply type: <info>php %command.full_name% [password] -n</info>. The salt will be generated
    for you, and the configuration of the <comment>Symfony\Component\Security\Core\User\User</comment> class will be taken to grab the right encoder.
    - You can also provide the salt and the user class by typing: <info>php %command.full_name% [password] --salt=[salt] --user-class=[namespace-class]</info>

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
        $salt = $input->getOption('salt');
        $userClass = $input->getOption('user-class');

        $helper = $this->getHelper('question');

        if (!$password) {
            $passwordQuestion = $this->createPasswordQuestion($input, $output);
            $password = $helper->ask($input, $output, $passwordQuestion);
        }

        if (!$userClass) {

            if ($input->isInteractive()) {
                $userClassQuestion = $this->createUserClassQuestion($input, $output);
                $userClass = $helper->ask($input, $output, $userClassQuestion);
            } else {
                $userClass = 'Symfony\Component\Security\Core\User\User';
            }
        }
        $encoder = $this->getContainer()->get('security.encoder_factory')->getEncoder($userClass);

        if ($encoder instanceof \Symfony\Component\Security\Core\Encoder\BCryptPasswordEncoder) {
            $salt = null;
            $output->writeln('<comment>As the type of the encoder is Symfony\Component\Security\Core\Encoder\BCryptPasswordEncoder, it is preferable to not provide any salt.</comment>');
        } elseif (!$salt) {
            if ($input->isInteractive()) {
                $saltQuestion = $this->createSaltQuestion($input, $output);
                $salt = $helper->ask($input, $output, $saltQuestion);
            } else {
                $salt = $this->generateSalt($output);
            }
        }

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
        $output->writeln('<comment>Caution: It is strongly recommended that you do not generate your own salt for this function. It will create a secure salt automatically for you if you do not specify one.</comment>');
        $saltQuestion = new Question("\n > (Optional) <question>Provide a salt (press <enter> to generate one):</question> ");

        $that = $this;
        $saltQuestion->setValidator(function ($value) use ($output, $that) {
            if ('' === trim($value)) {
                $output->writeln("\n<comment>The salt has been generated: </comment>".$value);
                $output->writeln(sprintf("<comment>Make sure that your salt storage field fits this salt length: %s chars.</comment>\n", strlen($value)));
                $value = $that->generateSalt($output);
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

    /**
     * @internal
     */
    public function generateSalt(OutputInterface $output)
    {
        $value = base64_encode($this->getContainer()->get('security.secure_random')->nextBytes(30));

        $output->writeln(sprintf("\n<comment>The salt has been generated: %s</comment>", $value));
        $output->writeln(sprintf("<comment>Make sure that your salt storage field fits this salt length: %s chars.</comment>\n", strlen($value)));

        return $value;
    }
}
