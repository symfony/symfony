<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler\Field;

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 *
 * @internal
 */
trait FormFieldTrait
{
    protected string $name;
    protected string|array|null $value = null;
    protected bool $disabled = false;

    /**
     * Returns the name of the field.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Gets the value of the field.
     */
    public function getValue(): string|array|null
    {
        return $this->value;
    }

    /**
     * Sets the value of the field.
     */
    public function setValue(?string $value): void
    {
        $this->value = $value ?? '';
    }

    /**
     * Returns true if the field should be included in the submitted values.
     */
    public function hasValue(): bool
    {
        return true;
    }


    /**
     * Initializes the form field.
     */
    abstract protected function initialize(): void;
}
