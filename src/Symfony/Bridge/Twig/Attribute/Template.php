<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Attribute;

/**
 * The Template class handles the Template attribute parts.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class Template
{
    protected ?string $template;
    private array $vars = [];
    private bool $streamable = false;
    private array $owner = [];

    public function __construct(
        string $template = null,
        array $vars = [],
        bool $isStreamable = false,
        array $owner = []
    ) {
        if (null !== $template) {
            $this->template = $template;
        }

        $this->setVars($vars);
        $this->setIsStreamable($isStreamable);
        $this->setOwner($owner);
    }

    public function getVars(): array
    {
        return $this->vars;
    }

    public function setIsStreamable(bool $streamable): void
    {
        $this->streamable = $streamable;
    }

    public function isStreamable(): bool
    {
        return $this->streamable;
    }

    public function setVars(array $vars): void
    {
        $this->vars = $vars;
    }

    public function getTemplate(): ?string
    {
        return $this->template;
    }

    public function setTemplate(?string $template): void
    {
        $this->template = $template;
    }

    public function setOwner(array $owner): void
    {
        $this->owner = $owner;
    }

    /**
     * The controller (+action) this annotation is attached to.
     */
    public function getOwner(): array
    {
        return $this->owner;
    }
}
