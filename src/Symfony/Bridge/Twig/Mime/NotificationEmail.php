<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Mime;

use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Part\AbstractPart;
use Twig\Extra\CssInliner\CssInlinerExtension;
use Twig\Extra\Inky\InkyExtension;
use Twig\Extra\Markdown\MarkdownExtension;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class NotificationEmail extends TemplatedEmail
{
    public const IMPORTANCE_URGENT = 'urgent';
    public const IMPORTANCE_HIGH = 'high';
    public const IMPORTANCE_MEDIUM = 'medium';
    public const IMPORTANCE_LOW = 'low';

    private string $theme = 'default';
    private array $context = [
        'importance' => self::IMPORTANCE_LOW,
        'content' => '',
        'exception' => false,
        'action_text' => null,
        'action_url' => null,
        'markdown' => false,
        'raw' => false,
        'footer_text' => 'Notification e-mail sent by Symfony',
    ];

    public function __construct(Headers $headers = null, AbstractPart $body = null)
    {
        $missingPackages = [];
        if (!class_exists(CssInlinerExtension::class)) {
            $missingPackages['twig/cssinliner-extra'] = 'CSS Inliner';
        }

        if (!class_exists(InkyExtension::class)) {
            $missingPackages['twig/inky-extra'] = 'Inky';
        }

        if ($missingPackages) {
            throw new \LogicException(sprintf('You cannot use "%s" if the "%s" Twig extension%s not available; try running "%s".', static::class, implode('" and "', $missingPackages), \count($missingPackages) > 1 ? 's are' : ' is', 'composer require '.implode(' ', array_keys($missingPackages))));
        }

        parent::__construct($headers, $body);
    }

    /**
     * Creates a NotificationEmail instance that is appropriate to send to normal (non-admin) users.
     */
    public static function asPublicEmail(Headers $headers = null, AbstractPart $body = null): self
    {
        $email = new static($headers, $body);
        $email->markAsPublic();

        return $email;
    }

    /**
     * @return $this
     */
    public function markAsPublic(): static
    {
        $this->context['importance'] = null;
        $this->context['footer_text'] = null;

        return $this;
    }

    /**
     * @return $this
     */
    public function markdown(string $content): static
    {
        if (!class_exists(MarkdownExtension::class)) {
            throw new \LogicException(sprintf('You cannot use "%s" if the Markdown Twig extension is not available; try running "composer require twig/markdown-extra".', __METHOD__));
        }

        $this->context['markdown'] = true;

        return $this->content($content);
    }

    /**
     * @return $this
     */
    public function content(string $content, bool $raw = false): static
    {
        $this->context['content'] = $content;
        $this->context['raw'] = $raw;

        return $this;
    }

    /**
     * @return $this
     */
    public function action(string $text, string $url): static
    {
        $this->context['action_text'] = $text;
        $this->context['action_url'] = $url;

        return $this;
    }

    /**
     * @return $this
     */
    public function importance(string $importance): static
    {
        $this->context['importance'] = $importance;

        return $this;
    }

    /**
     * @return $this
     */
    public function exception(\Throwable|FlattenException $exception): static
    {
        $exceptionAsString = $this->getExceptionAsString($exception);

        $this->context['exception'] = true;
        $this->attach($exceptionAsString, 'exception.txt', 'text/plain');
        $this->importance(self::IMPORTANCE_URGENT);

        if (!$this->getSubject()) {
            $this->subject($exception->getMessage());
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function theme(string $theme): static
    {
        $this->theme = $theme;

        return $this;
    }

    public function getTextTemplate(): ?string
    {
        if ($template = parent::getTextTemplate()) {
            return $template;
        }

        return '@email/'.$this->theme.'/notification/body.txt.twig';
    }

    public function getHtmlTemplate(): ?string
    {
        if ($template = parent::getHtmlTemplate()) {
            return $template;
        }

        return '@email/'.$this->theme.'/notification/body.html.twig';
    }

    public function getContext(): array
    {
        return array_merge($this->context, parent::getContext());
    }

    public function getPreparedHeaders(): Headers
    {
        $headers = parent::getPreparedHeaders();

        $importance = $this->context['importance'] ?? self::IMPORTANCE_LOW;
        $this->priority($this->determinePriority($importance));
        if ($this->context['importance']) {
            $headers->setHeaderBody('Text', 'Subject', sprintf('[%s] %s', strtoupper($importance), $this->getSubject()));
        }

        return $headers;
    }

    private function determinePriority(string $importance): int
    {
        return match ($importance) {
            self::IMPORTANCE_URGENT => self::PRIORITY_HIGHEST,
            self::IMPORTANCE_HIGH => self::PRIORITY_HIGH,
            self::IMPORTANCE_MEDIUM => self::PRIORITY_NORMAL,
            default => self::PRIORITY_LOW,
        };
    }

    private function getExceptionAsString(\Throwable|FlattenException $exception): string
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

    /**
     * @internal
     */
    public function __serialize(): array
    {
        return [$this->context, $this->theme, parent::__serialize()];
    }

    /**
     * @internal
     */
    public function __unserialize(array $data): void
    {
        if (3 === \count($data)) {
            [$this->context, $this->theme, $parentData] = $data;
        } else {
            // Backwards compatibility for deserializing data structures that were serialized without the theme
            [$this->context, $parentData] = $data;
        }

        parent::__unserialize($parentData);
    }
}
