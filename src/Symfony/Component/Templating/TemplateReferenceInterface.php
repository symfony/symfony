<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating;

/**
 * Interface to be implemented by all templates.
 *
 * @author Victor Berchet <victor@suumit.com>
 */
interface TemplateReferenceInterface
{
    /**
     * Gets the template parameters.
     */
    public function all(): array;

    /**
     * Sets a template parameter.
     *
     * @return $this
     *
     * @throws \InvalidArgumentException if the parameter name is not supported
     */
    public function set(string $name, string $value): static;

    /**
     * Gets a template parameter.
     *
     * @throws \InvalidArgumentException if the parameter name is not supported
     */
    public function get(string $name): string;

    /**
     * Returns the path to the template.
     *
     * By default, it just returns the template name.
     */
    public function getPath(): string;

    /**
     * Returns the "logical" template name.
     *
     * The template name acts as a unique identifier for the template.
     */
    public function getLogicalName(): string;

    /**
     * Returns the string representation as shortcut for getLogicalName().
     *
     * Alias of getLogicalName().
     */
    public function __toString(): string;
}
