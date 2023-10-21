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

trigger_deprecation('symfony/templating', '6.4', '"%s" is deprecated since version 6.4 and will be removed in 7.0. Use Twig instead.', TemplateReferenceInterface::class);

/**
 * Interface to be implemented by all templates.
 *
 * @author Victor Berchet <victor@suumit.com>
 *
 * @deprecated since Symfony 6.4, use Twig instead
 */
interface TemplateReferenceInterface extends \Stringable
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
