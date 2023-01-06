<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Notification;

use Psr\Log\LogLevel;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Notification
{
    private const LEVELS = [
        LogLevel::DEBUG => 100,
        LogLevel::INFO => 200,
        LogLevel::NOTICE => 250,
        LogLevel::WARNING => 300,
        LogLevel::ERROR => 400,
        LogLevel::CRITICAL => 500,
        LogLevel::ALERT => 550,
        LogLevel::EMERGENCY => 600,
    ];

    public const IMPORTANCE_URGENT = 'urgent';
    public const IMPORTANCE_HIGH = 'high';
    public const IMPORTANCE_MEDIUM = 'medium';
    public const IMPORTANCE_LOW = 'low';

    private array $channels = [];
    private string $subject = '';
    private string $content = '';
    private string $emoji = '';
    private ?FlattenException $exception = null;
    private string $exceptionAsString = '';
    private string $importance = self::IMPORTANCE_HIGH;

    /**
     * @param list<string> $channels
     */
    public function __construct(string $subject = '', array $channels = [])
    {
        $this->subject = $subject;
        $this->channels = $channels;
    }

    /**
     * @param list<string> $channels
     */
    public static function fromThrowable(\Throwable $exception, array $channels = []): self
    {
        return (new self('', $channels))->exception($exception);
    }

    /**
     * @return $this
     */
    public function subject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    /**
     * @return $this
     */
    public function content(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @return $this
     */
    public function importance(string $importance): static
    {
        $this->importance = $importance;

        return $this;
    }

    public function getImportance(): string
    {
        return $this->importance;
    }

    /**
     * @param string $level A PSR Logger log level name
     *
     * @return $this
     */
    public function importanceFromLogLevelName(string $level): static
    {
        $level = self::LEVELS[strtolower($level)];
        $this->importance = $level >= 500 ? self::IMPORTANCE_URGENT : ($level >= 400 ? self::IMPORTANCE_HIGH : self::IMPORTANCE_LOW);

        return $this;
    }

    /**
     * @return $this
     */
    public function emoji(string $emoji): static
    {
        $this->emoji = $emoji;

        return $this;
    }

    public function getEmoji(): string
    {
        return $this->emoji ?: $this->getDefaultEmoji();
    }

    /**
     * @return $this
     */
    public function exception(\Throwable $exception): static
    {
        $parts = explode('\\', $exception::class);

        $this->subject = sprintf('%s: %s', array_pop($parts), $exception->getMessage());
        if (class_exists(FlattenException::class)) {
            $this->exception = $exception instanceof FlattenException ? $exception : FlattenException::createFromThrowable($exception);
        }
        $this->exceptionAsString = $this->computeExceptionAsString($exception);

        return $this;
    }

    public function getException(): ?FlattenException
    {
        return $this->exception;
    }

    public function getExceptionAsString(): string
    {
        return $this->exceptionAsString;
    }

    /**
     * @param list<string> $channels
     *
     * @return $this
     */
    public function channels(array $channels): static
    {
        $this->channels = $channels;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getChannels(RecipientInterface $recipient): array
    {
        return $this->channels;
    }

    protected function getDefaultEmoji(): string
    {
        if (!$this->exceptionAsString) {
            return '';
        }

        return match ($this->importance) {
            self::IMPORTANCE_URGENT => '🌩️',
            self::IMPORTANCE_HIGH => '🌧️',
            self::IMPORTANCE_MEDIUM => '🌦️',
            default => '⛅',
        };
    }

    private function computeExceptionAsString(\Throwable $exception): string
    {
        if (class_exists(FlattenException::class)) {
            $exception = $exception instanceof FlattenException ? $exception : FlattenException::createFromThrowable($exception);

            return $exception->getAsString();
        }

        $message = $exception::class;
        if ('' !== $exception->getMessage()) {
            $message .= ': '.$exception->getMessage();
        }

        $message .= ' in '.$exception->getFile().':'.$exception->getLine()."\n";
        $message .= "Stack trace:\n".$exception->getTraceAsString()."\n\n";

        return rtrim($message);
    }
}
