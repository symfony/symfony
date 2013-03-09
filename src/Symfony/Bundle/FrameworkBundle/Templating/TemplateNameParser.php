<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Templating;

use Symfony\Component\Templating\TemplateNameParserInterface;
use Symfony\Component\Templating\TemplateReferenceInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * TemplateNameParser converts template names from the short notation
 * "bundle:section:template.format.engine" to TemplateReferenceInterface
 * instances.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TemplateNameParser implements TemplateNameParserInterface
{
    protected $kernel;
    protected $cache;

    /**
     * Constructor.
     *
     * @param KernelInterface $kernel A KernelInterface instance
     */
    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
        $this->cache = array();
    }

    /**
     * {@inheritdoc}
     */
    public function parse($name)
    {
        if ($name instanceof TemplateReferenceInterface) {
            return $name;
        } elseif (isset($this->cache[$name])) {
            return $this->cache[$name];
        }

        // normalize name
        $name = str_replace(':/', ':', preg_replace('#/{2,}#', '/', strtr($name, '\\', '/')));

        if (false !== strpos($name, '..')) {
            throw new \RuntimeException(sprintf('Template name "%s" contains invalid characters.', $name));
        }

        $parts = explode(':', $name);
        if (3 !== count($parts)) {
            throw new \InvalidArgumentException(sprintf('Template name "%s" is not valid (format is "bundle:section:template.format.engine").', $name));
        }

        $elements = explode('.', $parts[2]);
        if (3 > count($elements)) {
            throw new \InvalidArgumentException(sprintf('Template name "%s" is not valid (format is "bundle:section:template.format.engine").', $name));
        }
        $engine = array_pop($elements);
        $format = array_pop($elements);

        $template = new TemplateReference($parts[0], $parts[1], implode('.', $elements), $format, $engine);

        if ($template->get('bundle')) {
            try {
                $this->kernel->getBundle($template->get('bundle'));
            } catch (\Exception $e) {
                throw new \InvalidArgumentException(sprintf('Template name "%s" is not valid.', $name), 0, $e);
            }
        }

        return $this->cache[$name] = $template;
    }
}
