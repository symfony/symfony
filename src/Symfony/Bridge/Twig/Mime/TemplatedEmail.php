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

use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Mime\Email;
use Twig\TemplateWrapper;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TemplatedEmail extends Email
{
    private null|string|TemplateWrapper|array $htmlTemplate = null;
    private null|string|TemplateWrapper|array $textTemplate = null;
    private array $context = [];

    /**
     * @return $this
     */
    public function textTemplate(null|string|TemplateWrapper|array $template): static
    {
        if (\is_array($template) && !isset($template['templateName'])) {
            throw new InvalidArgumentException('Array parameter is only allowed as json serialized version of a TemplateWrapper.');
        }

        $this->textTemplate = $template;

        return $this;
    }

    /**
     * @return $this
     */
    public function htmlTemplate(null|string|TemplateWrapper|array $template): static
    {
        if (\is_array($template) && !isset($template['templateName'])) {
            throw new InvalidArgumentException('Array parameter is only allowed as json serialized version of a TemplateWrapper.');
        }

        $this->htmlTemplate = $template;

        return $this;
    }

    public function getTextTemplate(): null|string|TemplateWrapper
    {
        if (\is_array($this->textTemplate)) {
            return $this->textTemplate['templateName'];
        }

        return $this->textTemplate;
    }

    public function getHtmlTemplate(): null|string|TemplateWrapper
    {
        if (\is_array($this->htmlTemplate)) {
            return $this->htmlTemplate['templateName'];
        }

        return $this->htmlTemplate;
    }

    /**
     * @return $this
     */
    public function context(array $context): static
    {
        $this->context = $context;

        return $this;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function isRendered(): bool
    {
        return null === $this->htmlTemplate && null === $this->textTemplate;
    }

    public function markAsRendered(): void
    {
        $this->textTemplate = null;
        $this->htmlTemplate = null;
        $this->context = [];
    }

    /**
     * @internal
     */
    public function __serialize(): array
    {
        return [$this->htmlTemplate, $this->textTemplate, $this->context, parent::__serialize()];
    }

    /**
     * @internal
     */
    public function __unserialize(array $data): void
    {
        [$this->htmlTemplate, $this->textTemplate, $this->context, $parentData] = $data;

        parent::__unserialize($parentData);
    }
}
