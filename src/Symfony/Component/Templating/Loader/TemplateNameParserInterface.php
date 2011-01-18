<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating\Loader;

/**
 * TemplateNameParserInterface parses template name to a template name and an array of options.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface TemplateNameParserInterface
{
    /**
     * Parses a template to an array of parameters.
     *
     * The only mandatory parameter is the template name (name).
     *
     * @param string $name A template name
     *
     * @return array An array of parameters
     */
    function parse($name);
}
