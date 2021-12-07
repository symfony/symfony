<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Handler;

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\HtmlFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * @author Alexander Borisov <boshurik@gmail.com>
 */
class MailerHandler extends AbstractProcessingHandler
{
    private $mailer;
    private $messageTemplate;

    public function __construct(MailerInterface $mailer, callable|Email $messageTemplate, string|int $level = Logger::DEBUG, bool $bubble = true)
    {
        parent::__construct($level, $bubble);

        $this->mailer = $mailer;
        $this->messageTemplate = !\is_callable($messageTemplate) || $messageTemplate instanceof \Closure ? $messageTemplate : \Closure::fromCallable($messageTemplate);
    }

    /**
     * {@inheritdoc}
     */
    public function handleBatch(array $records): void
    {
        $messages = [];

        foreach ($records as $record) {
            if ($record['level'] < $this->level) {
                continue;
            }
            $messages[] = $this->processRecord($record);
        }

        if (!empty($messages)) {
            $this->send((string) $this->getFormatter()->formatBatch($messages), $messages);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function write(array $record): void
    {
        $this->send((string) $record['formatted'], [$record]);
    }

    /**
     * Send a mail with the given content.
     *
     * @param string $content formatted email body to be sent
     * @param array  $records the array of log records that formed this content
     */
    protected function send(string $content, array $records)
    {
        $this->mailer->send($this->buildMessage($content, $records));
    }

    /**
     * Gets the formatter for the Message subject.
     *
     * @param string $format The format of the subject
     */
    protected function getSubjectFormatter(string $format): FormatterInterface
    {
        return new LineFormatter($format);
    }

    /**
     * Creates instance of Message to be sent.
     *
     * @param string $content formatted email body to be sent
     * @param array  $records Log records that formed the content
     */
    protected function buildMessage(string $content, array $records): Email
    {
        $message = null;
        if ($this->messageTemplate instanceof Email) {
            $message = clone $this->messageTemplate;
        } elseif (\is_callable($this->messageTemplate)) {
            $message = ($this->messageTemplate)($content, $records);
            if (!$message instanceof Email) {
                throw new \InvalidArgumentException(sprintf('Could not resolve message from a callable. Instance of "%s" is expected.', Email::class));
            }
        } else {
            throw new \InvalidArgumentException('Could not resolve message as instance of Email or a callable returning it.');
        }

        if ($records) {
            $subjectFormatter = $this->getSubjectFormatter($message->getSubject());
            $message->subject($subjectFormatter->format($this->getHighestRecord($records)));
        }

        if ($this->getFormatter() instanceof HtmlFormatter) {
            if ($message->getHtmlCharset()) {
                $message->html($content, $message->getHtmlCharset());
            } else {
                $message->html($content);
            }
        } else {
            if ($message->getTextCharset()) {
                $message->text($content, $message->getTextCharset());
            } else {
                $message->text($content);
            }
        }

        return $message;
    }

    protected function getHighestRecord(array $records): array
    {
        $highestRecord = null;
        foreach ($records as $record) {
            if (null === $highestRecord || $highestRecord['level'] < $record['level']) {
                $highestRecord = $record;
            }
        }

        return $highestRecord;
    }
}
