<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo;

/**
 * The write mutator defines how a property can be written.
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 *
 * @internal
 */
final class PropertyWriteInfo
{
    public const TYPE_NONE = 'none';
    public const TYPE_METHOD = 'method';
    public const TYPE_PROPERTY = 'property';
    public const TYPE_ADDER_AND_REMOVER = 'adder_and_remover';
    public const TYPE_CONSTRUCTOR = 'constructor';

    public const VISIBILITY_PUBLIC = 'public';
    public const VISIBILITY_PROTECTED = 'protected';
    public const VISIBILITY_PRIVATE = 'private';

    private $type;
    private $name;
    private $visibility;
    private $static;
    private $adderInfo;
    private $removerInfo;
    private $errors = [];

    public function __construct(string $type = self::TYPE_NONE, ?string $name = null, ?string $visibility = null, ?bool $static = null)
    {
        $this->type = $type;
        $this->name = $name;
        $this->visibility = $visibility;
        $this->static = $static;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getName(): string
    {
        if (null === $this->name) {
            throw new \LogicException("Calling getName() when having a mutator of type {$this->type} is not tolerated.");
        }

        return $this->name;
    }

    public function setAdderInfo(self $adderInfo): void
    {
        $this->adderInfo = $adderInfo;
    }

    public function getAdderInfo(): self
    {
        if (null === $this->adderInfo) {
            throw new \LogicException("Calling getAdderInfo() when having a mutator of type {$this->type} is not tolerated.");
        }

        return $this->adderInfo;
    }

    public function setRemoverInfo(self $removerInfo): void
    {
        $this->removerInfo = $removerInfo;
    }

    public function getRemoverInfo(): self
    {
        if (null === $this->removerInfo) {
            throw new \LogicException("Calling getRemoverInfo() when having a mutator of type {$this->type} is not tolerated.");
        }

        return $this->removerInfo;
    }

    public function getVisibility(): string
    {
        if (null === $this->visibility) {
            throw new \LogicException("Calling getVisibility() when having a mutator of type {$this->type} is not tolerated.");
        }

        return $this->visibility;
    }

    public function isStatic(): bool
    {
        if (null === $this->static) {
            throw new \LogicException("Calling isStatic() when having a mutator of type {$this->type} is not tolerated.");
        }

        return $this->static;
    }

    public function setErrors(array $errors): void
    {
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return (bool) \count($this->errors);
    }
}
