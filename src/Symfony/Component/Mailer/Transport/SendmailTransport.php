<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Transport;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\AbstractStream;
use Symfony\Component\Mailer\Transport\Smtp\Stream\ProcessStream;
use Symfony\Component\Mime\RawMessage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * SendmailTransport for sending mail through a Sendmail/Postfix (etc..) binary.
 *
 * Supported modes are -bs and -t, with any additional flags desired.
 * It is advised to use -bs mode since error reporting with -t mode is not
 * possible.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Chris Corbyn
 */
class SendmailTransport extends AbstractTransport
{
    private $command = '/usr/sbin/sendmail -bs';
    private $stream;
    private $transport;

    /**
     * Constructor.
     *
     * If using -t mode you are strongly advised to include -oi or -i in the flags.
     * For example: /usr/sbin/sendmail -oi -t
     * -f<sender> flag will be appended automatically if one is not present.
     *
     * The recommended mode is "-bs" since it is interactive and failure notifications are hence possible.
     */
    public function __construct(string $command = null, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        parent::__construct($dispatcher, $logger);

        if (null !== $command) {
            if (!str_contains($command, ' -bs') && !str_contains($command, ' -t')) {
                throw new \InvalidArgumentException(sprintf('Unsupported sendmail command flags "%s"; must be one of "-bs" or "-t" but can include additional flags.', $command));
            }

            $this->command = $command;
        }

        $this->stream = new ProcessStream();
        if (str_contains($this->command, ' -bs')) {
            $this->stream->setCommand($this->command);
            $this->transport = new SmtpTransport($this->stream, $dispatcher, $logger);
        }
    }

    public function send(RawMessage $message, Envelope $envelope = null): ?SentMessage
    {
        if ($this->transport) {
            return $this->transport->send($message, $envelope);
        }

        return parent::send($message, $envelope);
    }

    public function __toString(): string
    {
        if ($this->transport) {
            return (string) $this->transport;
        }

        return 'smtp://sendmail';
    }

    protected function doSend(SentMessage $message): void
    {
        $this->getLogger()->debug(sprintf('Email transport "%s" starting', __CLASS__));

        $command = $this->command;
        if (!str_contains($command, ' -f')) {
            $command .= ' -f'.escapeshellarg($message->getEnvelope()->getSender()->getEncodedAddress());
        }

        $chunks = AbstractStream::replace("\r\n", "\n", $message->toIterable());

        if (!str_contains($command, ' -i') && !str_contains($command, ' -oi')) {
            $chunks = AbstractStream::replace("\n.", "\n..", $chunks);
        }

        $this->stream->setCommand($command);
        $this->stream->initialize();
        foreach ($chunks as $chunk) {
            $this->stream->write($chunk);
        }
        $this->stream->flush();
        $this->stream->terminate();

        $this->getLogger()->debug(sprintf('Email transport "%s" stopped', __CLASS__));
    }
}
