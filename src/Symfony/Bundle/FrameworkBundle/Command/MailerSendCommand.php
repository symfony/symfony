<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * A console command to send an email via Symfony Mailer.
 *
 * @author Fritz Michael Gschwantner <fmg@inspiredminds.at>
 */
final class MailerSendCommand extends Command
{
    protected static $defaultName = 'mailer:send';
    protected static $defaultDescription = 'Sends an email message';

    private $io;
    private $mailer;

    public function __construct(MailerInterface $mailer)
    {
        parent::__construct();

        $this->mailer = $mailer;
    }

    protected function configure()
    {
        $this
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'The sender of the message')
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'The recipient of the message')
            ->addOption('subject', null, InputOption::VALUE_REQUIRED, 'The subject of the message')
            ->addOption('body', null, InputOption::VALUE_REQUIRED, 'The body of the message')
            ->addOption('transport', null, InputOption::VALUE_OPTIONAL, 'The transport to be used')
            ->addOption('content-type', null, InputOption::VALUE_REQUIRED, 'The body content type of the message', 'text/plain')
            ->addOption('charset', null, InputOption::VALUE_REQUIRED, 'The body charset of the message', 'utf-8')
            ->addOption('body-source', null, InputOption::VALUE_REQUIRED, 'The source of the body [stdin|file]', 'stdin')
            ->setHelp(
                <<<EOF
The <info>%command.name%</info> command creates and sends a simple email message.

<info>php %command.full_name% --transport=custom_transport --content-type=text/html</info>

You can get the body of a message from a file:
<info>php %command.full_name% --body-source=file --body=/path/to/file</info>

EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        switch ($input->getOption('body-source')) {
            case 'file':
                $filename = $input->getOption('body');
                $content = file_get_contents($filename);
                if (false === $content) {
                    throw new \Exception(sprintf('Could not get contents from "%s".', $filename));
                }
                $input->setOption('body', $content);
                break;
            case 'stdin':
                break;
            default:
                throw new \InvalidArgumentException('body-source option should be "stdin" or "file".');
        }

        $this->mailer->send($this->createMessage($input));
        $this->io->success('Email was successfully sent.');

        return Command::SUCCESS;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Symfony Mailer\'s Interactive Email Sender');
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        foreach ($input->getOptions() as $option => $value) {
            if (null === $value && 'transport' !== $option) {
                $input->setOption($option, $this->io->ask(sprintf('%s', ucfirst($option))));
            }
        }
    }

    private function createMessage(InputInterface $input): Email
    {
        $contentType = $input->getOption('content-type');

        if (!\in_array($contentType, ['text/plain', 'text/html'], true)) {
            throw new \InvalidArgumentException(sprintf('Invalid content-type "%s", only "text/plain" and "text/html" allowed.', $contentType));
        }

        $type = 'text/html' === $contentType ? 'html' : 'text';

        $message = (new Email())
            ->subject($input->getOption('subject'))
            ->from($input->getOption('from'))
            ->to($input->getOption('to'))
            ->{$type}($input->getOption('body'), $input->getOption('charset'))
        ;

        if ($transport = $input->getOption('transport')) {
            $message->getHeaders()->addTextHeader('X-Transport', $transport);
        }

        return $message;
    }
}
