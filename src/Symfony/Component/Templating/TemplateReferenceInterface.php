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
     *
     * @return array An array of parameters
     */
    public function all();

    /**
     * Sets a template parameter.
     *
     * @param string $name  The parameter name
     * @param string $value The parameter value
     *
     * @return $this
     *
     * @throws \InvalidArgumentException if the parameter name is not supported
     */
    public function set($name, $value);

    /**
     * Gets a template parameter.
     *
     * @param string $name The parameter name
     *
     * @return string The parameter value
     *
     * @throws \InvalidArgumentException if the parameter name is not supported
     */
    public function get($name);

    /**
     * Returns the path to the template.
     *
     * By default, it just returns the template name.
     *
     * @return string A path to the template or a resource
     */
    public function getPath();

    /**
     * Returns the "logical" template name.
     *
     * The template name acts as a unique identifier for the template.
     *
     * @return string The template name
     */
    public function getLogicalName();

    /**
     * Returns the string representation as shortcut for getLogicalName().
     *
     * Alias of getLogicalName().
     *
     * @return string The template name
     */
    public function __toString();
}
