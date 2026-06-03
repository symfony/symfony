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
use Monolog\Level;
use Monolog\LogRecord;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * @author Alexander Borisov <boshurik@gmail.com>
 */
final class MailerHandler extends AbstractProcessingHandler
{
    public const TRUNCATION_MARKER = '[...]';

    private \Closure|Email $messageTemplate;

    /**
     * @param int<0, max> $subjectMaxLength The maximum number of characters of the formatted subject;
     *                                      pass 0 to disable truncation, otherwise the value must be at
     *                                      least the length of self::TRUNCATION_MARKER. When triggered,
     *                                      the trailing characters are replaced with the marker, so the
     *                                      resulting subject never exceeds $subjectMaxLength characters.
     */
    public function __construct(
        private MailerInterface $mailer,
        callable|Email $messageTemplate,
        string|int|Level $level = Level::Debug,
        bool $bubble = true,
        private int $subjectMaxLength = 200,
    ) {
        if ($subjectMaxLength < 0 || (0 < $subjectMaxLength && $subjectMaxLength < \strlen(self::TRUNCATION_MARKER))) {
            throw new \InvalidArgumentException(\sprintf('The "$subjectMaxLength" argument must be 0 (to disable truncation) or at least %d (the length of the "%s" marker).', \strlen(self::TRUNCATION_MARKER), self::TRUNCATION_MARKER));
        }

        parent::__construct($level, $bubble);

        $this->messageTemplate = $messageTemplate instanceof Email ? $messageTemplate : $messageTemplate(...);
    }

    public function handleBatch(array $records): void
    {
        $messages = [];

        foreach ($records as $record) {
            if ($record->level->isLowerThan($this->level)) {
                continue;
            }
            $messages[] = $this->processRecord($record);
        }

        if ($messages) {
            $this->send((string) $this->getFormatter()->formatBatch($messages), $messages);
        }
    }

    protected function write(LogRecord $record): void
    {
        $this->send((string) $record->formatted, [$record]);
    }

    /**
     * Send a mail with the given content.
     *
     * @param string $content formatted email body to be sent
     * @param array  $records the array of log records that formed this content
     */
    protected function send(string $content, array $records): void
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
        if ($this->messageTemplate instanceof Email) {
            $message = clone $this->messageTemplate;
        } elseif (\is_callable($this->messageTemplate)) {
            $message = ($this->messageTemplate)($content, $records);
            if (!$message instanceof Email) {
                throw new \InvalidArgumentException(\sprintf('Could not resolve message from a callable. Instance of "%s" is expected.', Email::class));
            }
        } else {
            throw new \InvalidArgumentException('Could not resolve message as instance of Email or a callable returning it.');
        }

        if ($records) {
            $subjectFormatter = $this->getSubjectFormatter($message->getSubject());
            $subject = $subjectFormatter->format($this->getHighestRecord($records));

            if ($this->subjectMaxLength && mb_strlen($subject) > $this->subjectMaxLength) {
                $subject = mb_substr($subject, 0, $this->subjectMaxLength - \strlen(self::TRUNCATION_MARKER)).self::TRUNCATION_MARKER;
            }

            $message->subject($subject);
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

    protected function getHighestRecord(array $records): LogRecord
    {
        $highestRecord = null;
        foreach ($records as $record) {
            if (null === $highestRecord || $highestRecord->level->isLowerThan($record->level)) {
                $highestRecord = $record;
            }
        }

        return $highestRecord;
    }
}
