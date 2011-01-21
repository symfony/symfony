<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating;

/**
 * EngineInterface is the interface each engine must implement.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface EngineInterface
{
    /**
     * Renders a template.
     *
     * @param string $name       A template name
     * @param array  $parameters An array of parameters to pass to the template
     *
     * @return string The evaluated template as a string
     *
     * @throws \RuntimeException if the template cannot be rendered
     */
    function render($name, array $parameters = array());

    /**
     * Returns true if the template exists.
     *
     * @param string $name A template name
     *
     * @return Boolean true if the template exists, false otherwise
     */
    function exists($name);

    /**
     * Loads the given template.
     *
     * @param string $name A template name
     *
     * @return mixed A renderable template
     *
     * @throws \Exception if the template cannot be found
     */
    function load($name);

    /**
     * Returns true if this class is able to render the given template.
     *
     * @param string $name A template name
     *
     * @return Boolean True if this class supports the given template, false otherwise
     */
    function supports($name);
}
