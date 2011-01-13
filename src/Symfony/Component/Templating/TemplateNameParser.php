<?php

namespace Symfony\Component\Templating;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * TemplateNameParser is the default implementation of TemplateNameParserInterface.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class TemplateNameParser implements TemplateNameParserInterface
{
    protected $defaultOptions = array();

    /**
     * Parses a template to a template name and an array of options.
     *
     * @param string $name     A template name
     * @param array  $defaults An array of default options
     *
     * @return array An array composed of the template name and an array of options
     */
    public function parse($name, array $defaults = array())
    {
        return array($name, array_merge($this->defaultOptions, $defaults));
    }

    /**
     * Sets a default option.
     *
     * @param string $name  The option name
     * @param mixed  $value The option value
     */
    public function setDefaultOption($name, $value)
    {
        $this->defaultOptions[$name] = $value;
    }
}
