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

    private $theme = 'default';
    private $context = [
        'importance' => self::IMPORTANCE_LOW,
        'content' => '',
        'exception' => false,
        'action_text' => null,
        'action_url' => null,
        'markdown' => false,
        'raw' => false,
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
     * @return $this
     */
    public function markdown(string $content)
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
    public function content(string $content, bool $raw = false)
    {
        $this->context['content'] = $content;
        $this->context['raw'] = $raw;

        return $this;
    }

    /**
     * @return $this
     */
    public function action(string $text, string $url)
    {
        $this->context['action_text'] = $text;
        $this->context['action_url'] = $url;

        return $this;
    }

    /**
     * @return $this
     */
    public function importance(string $importance)
    {
        $this->context['importance'] = $importance;

        return $this;
    }

    /**
     * @param \Throwable|FlattenException $exception
     *
     * @return $this
     */
    public function exception($exception)
    {
        if (!$exception instanceof \Throwable && !$exception instanceof FlattenException) {
            throw new \LogicException(sprintf('"%s" accepts "%s" or "%s" instances.', __METHOD__, \Throwable::class, FlattenException::class));
        }

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
    public function theme(string $theme)
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
        $headers->setHeaderBody('Text', 'Subject', sprintf('[%s] %s', strtoupper($importance), $this->getSubject()));

        return $headers;
    }

    private function determinePriority(string $importance): int
    {
        switch ($importance) {
            case self::IMPORTANCE_URGENT:
                return self::PRIORITY_HIGHEST;
            case self::IMPORTANCE_HIGH:
                return self::PRIORITY_HIGH;
            case self::IMPORTANCE_MEDIUM:
                return self::PRIORITY_NORMAL;
            case self::IMPORTANCE_LOW:
            default:
                return self::PRIORITY_LOW;
        }
    }

    private function getExceptionAsString($exception): string
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
        return [$this->context, parent::__serialize()];
    }

    /**
     * @internal
     */
    public function __unserialize(array $data): void
    {
        [$this->context, $parentData] = $data;

        parent::__unserialize($parentData);
    }
}
