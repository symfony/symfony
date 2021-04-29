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
class Notification implements NotificationInterface
{
    protected const LEVELS = [
        LogLevel::DEBUG => 100,
        LogLevel::INFO => 200,
        LogLevel::NOTICE => 250,
        LogLevel::WARNING => 300,
        LogLevel::ERROR => 400,
        LogLevel::CRITICAL => 500,
        LogLevel::ALERT => 550,
        LogLevel::EMERGENCY => 600,
    ];

    protected $channels = [];
    protected $subject = '';
    protected $content = '';
    protected $emoji = '';
    protected $exception;
    protected $exceptionAsString = '';
    protected $importance = self::IMPORTANCE_HIGH;

    public function __construct(string $subject = '', array $channels = [])
    {
        $this->subject = $subject;
        $this->channels = $channels;
    }

    public static function fromThrowable(\Throwable $exception, array $channels = []): self
    {
        $parts = explode('\\', \get_class($exception));

        $notification = new self(sprintf('%s: %s', array_pop($parts), $exception->getMessage()), $channels);
        if (class_exists(FlattenException::class)) {
            $notification->exception = $exception instanceof FlattenException ? $exception : FlattenException::createFromThrowable($exception);
        }
        $notification->exceptionAsString = $notification->computeExceptionAsString($exception);

        return $notification;
    }

    /**
     * @return $this
     */
    public function setSubject(string $subject): self
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
    public function setContent(string $content): self
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
    public function setImportance(string $importance): self
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
    public function importanceFromLogLevelName(string $level): self
    {
        $level = self::LEVELS[strtolower($level)];
        $this->importance = $level >= 500 ? self::IMPORTANCE_URGENT : ($level >= 400 ? self::IMPORTANCE_HIGH : self::IMPORTANCE_LOW);

        return $this;
    }

    /**
     * @return $this
     */
    public function emoji(string $emoji): self
    {
        $this->emoji = $emoji;

        return $this;
    }

    public function getEmoji(): string
    {
        return $this->emoji ?: $this->getDefaultEmoji();
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
     * @return $this
     */
    public function setChannels(array $channels): self
    {
        $this->channels = $channels;

        return $this;
    }

    public function getChannels(RecipientInterface $recipient): array
    {
        return $this->channels;
    }

    protected function getDefaultEmoji(): string
    {
        if (!$this->exceptionAsString) {
            return '';
        }

        switch ($this->importance) {
            case self::IMPORTANCE_URGENT:
                return '🌩️';
            case self::IMPORTANCE_HIGH:
                return '🌧️';
            case self::IMPORTANCE_MEDIUM:
                return '🌦️';
            case self::IMPORTANCE_LOW:
            default:
                return '⛅';
        }
    }

    private function computeExceptionAsString(\Throwable $exception): string
    {
        if (class_exists(FlattenException::class)) {
            $exception = $exception instanceof FlattenException ? $exception : FlattenException::createFromThrowable($exception);

            return $exception->getAsString();
        }

        $message = \get_class($exception);
        if ('' !== $exception->getMessage()) {
            $message .= ': '.$exception->getMessage();
        }

        $message .= ' in '.$exception->getFile().':'.$exception->getLine()."\n";
        $message .= "Stack trace:\n".$exception->getTraceAsString()."\n\n";

        return rtrim($message);
    }
}
