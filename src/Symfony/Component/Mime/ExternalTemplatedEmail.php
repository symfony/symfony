<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime;

use Symfony\Component\Mime\Exception\LogicException;

class ExternalTemplatedEmail extends Email
{
    private ?string $templateId = null;
    private array $context = [];
    private ?string $locale = null;

    public function ensureValidity(): void
    {
        if (null === $this->getTemplateId()) {
            throw new LogicException('The template ID is required.');
        }

        if ('1' === $this->getHeaders()->getHeaderBody('X-Unsent')) {
            throw new LogicException('Cannot send messages marked as "draft".');
        }

        Message::ensureValidity();
    }

    public function templateId(?string $templateId): static
    {
        $this->templateId = $templateId;

        return $this;
    }

    public function getTemplateId(): ?string
    {
        return $this->templateId;
    }

    /**
     * @return $this
     */
    public function locale(?string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
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

    /**
     * @internal
     */
    public function __serialize(): array
    {
        return [$this->context, parent::__serialize(), $this->locale];
    }

    /**
     * @internal
     */
    public function __unserialize(array $data): void
    {
        [$this->context, $parentData] = $data;
        $this->locale = $data[2] ?? null;

        parent::__unserialize($parentData);
    }
}
