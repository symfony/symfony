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
    function all();

    /**
     * Sets a template parameter.
     *
     * @param string $name   The parameter name
     * @param string $value  The parameter value
     *
     * @return TemplateReferenceInterface The TemplateReferenceInterface instance
     *
     * @throws  \InvalidArgumentException if the parameter is not defined
     */
    function set($name, $value);

    /**
     * Gets a template parameter.
     *
     * @param string $name The parameter name
     *
     * @return string The parameter value
     *
     * @throws  \InvalidArgumentException if the parameter is not defined
     */
    function get($name);

    /**
     * Returns the template signature
     *
     * @return string A UID for the template
     */
    function getSignature();

    /**
     * Returns the path to the template.
     *
     * By default, it just returns the template name.
     *
     * @return string A path to the template or a resource
     */
    function getPath();

    /**
     * Returns the template name
     *
     * @return string The template name
     */
    function getLogicalName();
}
