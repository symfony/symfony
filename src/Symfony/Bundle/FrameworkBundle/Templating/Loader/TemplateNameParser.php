<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Templating\Loader;

use Symfony\Component\Templating\Loader\TemplateNameParser as BaseTemplateNameParser;
use Symfony\Component\HttpKernel\Kernel;

/**
 * TemplateNameParser parsers template name from the short notation
 * "bundle:section:template.renderer.format" to an array of
 * template parameters.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class TemplateNameParser extends BaseTemplateNameParser
{
    protected $kernel;

    /**
     * Constructor.
     *
     * @param Kernel $kernel A Kernel instance
     */
    public function __construct(Kernel $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * {@inheritdoc}
     */
    public function parse($name)
    {
        $parts = explode(':', $name);
        if (3 !== count($parts)) {
            throw new \InvalidArgumentException(sprintf('Template name "%s" is not valid (format is "bundle:section:template.renderer.format").', $name));
        }

        $elements = explode('.', $parts[2]);
        if (3 !== count($elements)) {
            throw new \InvalidArgumentException(sprintf('Template name "%s" is not valid (format is "bundle:section:template.renderer.format").', $name));
        }

        $parameters = array(
            'bundle'     => $parts[0],
            'controller' => $parts[1],
            'name'       => $elements[0],
            'format'     => $elements[1],
            'renderer'   => $elements[2],
        );

        if ($parameters['bundle']) {
            try {
                $this->kernel->getBundle($parameters['bundle']);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException(sprintf('Template name "%s" is not valid.', $name), 0, $e);
            }
        }

        return $parameters;
    }
}
