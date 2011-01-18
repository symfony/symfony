<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Templating;

use Symfony\Component\Templating\TemplateNameParser as BaseTemplateNameParser;
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

        $bundle = null;
        if ($parts[0]) {
            foreach ($this->kernel->getBundles() as $b) {
                if ($parts[0] !== $b->getName()) {
                    continue;
                }

                foreach (array_keys($this->kernel->getBundleDirs()) as $prefix) {
                    if (0 === $pos = strpos($b->getNamespace(), $prefix)) {
                        $bundle = str_replace($prefix.'\\', '', $b->getNamespace());

                        break 2;
                    }
                }
            }

            if (null === $bundle) {
                throw new \InvalidArgumentException(sprintf('Unable to find a valid bundle name for template "%s".', $name));
            }
        }

        $elements = explode('.', $parts[2]);
        if (3 !== count($elements)) {
            throw new \InvalidArgumentException(sprintf('Template name "%s" is not valid (format is "bundle:section:template.renderer.format").', $name));
        }

        $parameters = array(
            // bundle is used as part of the template path, so we need /
            'bundle'     => str_replace('\\', '/', $bundle),
            'controller' => $parts[1],
            'name'       => $elements[0],
            'renderer'   => $elements[1],
            'format'     => $elements[2],
        );


        return $parameters;
    }
}
