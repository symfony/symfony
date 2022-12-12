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

use Symfony\Component\Mime\Email;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TemplatedEmail extends Email
{
    private ?string $htmlTemplate = null;
    private ?string $textTemplate = null;
    private array $context = [];

    /**
     * @return $this
     */
    public function textTemplate(?string $template): static
    {
        $this->textTemplate = $template;

        return $this;
    }

    /**
     * @return $this
     */
    public function htmlTemplate(?string $template): static
    {
        $this->htmlTemplate = $template;

        return $this;
    }

    public function getTextTemplate(): ?string
    {
        return $this->textTemplate;
    }

    public function getHtmlTemplate(): ?string
    {
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
