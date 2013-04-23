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
    protected $bundles;

    /**
     * Constructor.
     *
     * @param KernelInterface $kernel A KernelInterface instance
     */
    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
        $this->bundles = $kernel->getBundles();
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

        if (false !== strpos($name, '..')) {
            throw new \RuntimeException(sprintf('Template name "%s" contains invalid characters.', $name));
        }

        if ($template = $this->parseColonName($name)) {
            return $this->cache[$name] = $template;
        }

        if ($template = $this->parseAtName($name)) {
            return $this->cache[$name] = $template;
        }

        // TODO throw
    }

    protected function parseColonName($name)
    {
        $name = str_replace(':/', ':', preg_replace('#/{2,}#', '/', strtr($name, '\\', '/')));

        $parts = explode(':', $name);
        if (3 !== count($parts)) {
            return null;
        }

        $elements = explode('.', $parts[2]);
        if (3 > count($elements)) {
            return null;
        }
        $engine = array_pop($elements);
        $format = array_pop($elements);

        $template = new TemplateReference($parts[0], $parts[1], implode('.', $elements), $format, $engine);

        if (!array_key_exists($template->get('bundle'), $this->bundles)) {
            throw new \InvalidArgumentException(sprintf('Template name "%s" is not valid.', $name));
        }

        return $template;
    }

    protected function parseAtName($name)
    {
        $name = preg_replace('#/{2,}#', '/', strtr($name, '\\', '/'));

        if (strlen($name) > 0 && '@' === $name[0] && $parts = explode('/', $name)) {
            if (count($parts) < 2) {
                die($name);
                return null;
            }

            $bundle = substr(array_shift($parts), 1);

            if (!array_key_exists($bundle, $this->bundles)) {
                $bundle = $bundle . 'Bundle';
                if (!array_key_exists($bundle, $this->bundles)) {
                    var_dump($bundle);
                    var_dump($this->bundles);
                    return null;
                }
            }

            $elements = explode('.', array_pop($parts));
            if (3 > count($elements)) {
                return null;
            }
            $engine = array_pop($elements);
            $format = array_pop($elements);

            return new TemplateReference($bundle, implode('/', $parts), implode('.', $elements), $format, $engine);
        }
    }
}
