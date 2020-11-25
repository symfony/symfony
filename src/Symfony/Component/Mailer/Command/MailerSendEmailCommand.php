<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Helps making sure your mailer provider is operational.
 *
 * @author Guillaume MOREL <me@gmorel.io>
 */
final class MailerSendEmailCommand extends Command
{
    /** {@inheritdoc} */
    protected static $defaultName = 'mailer:send-email';

    /** @var MailerInterface */
    private $mailer;

    /** @var SymfonyStyle */
    private $io;

    /**
     * {@inheritdoc}
     */
    public function __construct(MailerInterface $mailer)
    {
        parent::__construct();

        $this->mailer = $mailer;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setDescription('Send simple email message')
            ->addArgument('from', InputArgument::REQUIRED, 'The from address of the message')
            ->addArgument('to', InputArgument::REQUIRED, 'The to address of the message')
            ->addOption('subject', null, InputOption::VALUE_REQUIRED, 'The subject of the message', 'Testing Mailer Component')
            ->addOption('body', null, InputOption::VALUE_REQUIRED, 'The body of the message', 'This is a test email.')
            ->addOption('body-source', null, InputOption::VALUE_REQUIRED, 'The source where body come from [stdin|file]', 'stdin')
            ->setHelp(
                <<<EOF
The <info>%command.name%</info> command creates and sends a simple email message.
Usage:
- <info>php %command.full_name% from=a@symfony.com to=b@symfony.com</info>
- <info>php %command.full_name% from=a@symfony.com to=b@symfony.com --subject=Test --body=body</info>

You can get body of message from a file:
<info>php %command.full_name% from=a@symfony.com to=b@symfony.com --subject=Test --body-source=file --body=/path/to/file</info>
EOF
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    /**
     * {@inheritdoc}
     *
     * @throws TransportExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        switch ($input->getOption('body-source')) {
            case 'file':
                $content = $this->loadFileContent(
                    $input->getOption('body')
                );
                $input->setOption('body', $content);
                break;
            case 'stdin':
                break;
            default:
                throw new \InvalidArgumentException('Body-input option should be "stdin" or "file".');
        }

        if ('file' === $input->getOption('body-source')) {
            $email = $this->createEmailFromFile($input);
        } else {
            $email = $this->createEmailFromString($input);
        }

        $this->mailer->send($email);

        $this->io->success(
            sprintf(
                'Email was successfully sent to "%s".',
                (string) $input->getArgument('to')
            )
        );

        return Command::SUCCESS;
    }

    private function createEmailFromString(InputInterface $input): Email
    {
        $subject = $input->getOption('subject');
        $body = $input->getOption('body');

        return $this->createEmailWithoutBody($input)
            ->text($body)
            ->html(
                <<<HTML
<!doctype html>
<html>
  <head>
    <meta name="viewport" content="width=device-width" />
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>$subject</title>
  </head>
  <body>
    <p>$body</p>
  </body>
</html>
HTML
            );
    }

    private function createEmailFromFile(InputInterface $input): Email
    {
        $body = $input->getOption('body');

        return $this->createEmailWithoutBody($input)
            ->text($body)
            ->html($body);
    }

    private function createEmailWithoutBody(InputInterface $input): Email
    {
        return (new Email())
            ->from($input->getArgument('from'))
            ->to($input->getArgument('to'))
            ->priority(Email::PRIORITY_HIGH)
            ->subject($input->getOption('subject'));
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \LogicException
     */
    private function loadFileContent(string $fileUri): string
    {
        if (false === file_exists($fileUri)) {
            throw new \InvalidArgumentException("Could not find file \"$fileUri\".");
        }

        $content = file_get_contents($fileUri);
        if (false === $content) {
            throw new \LogicException("Could not get contents from file \"$fileUri\".");
        }

        return $content;
    }
}
