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
 * TemplateNameParser is the default implementation of TemplateNameParserInterface.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class TemplateNameParser implements TemplateNameParserInterface
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
    public function parse($name)
    {
        return array('name' => $name);
    }
}
