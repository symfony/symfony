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
 * TemplateNameParserInterface parses template names to a
 * "normalized" array of template parameters.
 *
 * The template name array must always have at least a "name"
 * and an "engine" key.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface TemplateNameParserInterface
{
    /**
     * Parses a template to an array of parameters.
     *
     * @param string $name A template name
     *
     * @return array An array of template parameters
     */
    function parse($name);
}
