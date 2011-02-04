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
 * All methods relies on a template name. A template name is a
 * "logical" name for the template (an array), and as such it does not
 * refers to a path on the filesystem (in fact, the template can be
 * stored anywhere, like in a database).
 *
 * The methods should accept any name and if it is not an array, it should
 * then use a TemplateNameParserInterface to convert the name to an array.
 *
 * Each template loader use the logical template name to look for
 * the template.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface EngineInterface
{
    /**
     * Renders a template.
     *
     * @param mixed $name       A template name
     * @param array $parameters An array of parameters to pass to the template
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
     * Returns true if this class is able to render the given template.
     *
     * @param string $name A template name
     *
     * @return Boolean True if this class supports the given template, false otherwise
     */
    function supports($name);
}
