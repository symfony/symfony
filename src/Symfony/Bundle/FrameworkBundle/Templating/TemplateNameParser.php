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

        if (!$name) {
            throw new \InvalidArgumentException('Template names should not be empty.');
        }

        // The following blocks are optimized for speed. Named sub-patterns
        // should not be used because they significantly lower the performance
        // of the code.
        if ('@' !== $name[0] && preg_match('#^([^:]*):([^:]*):(.+)\.([^\.]+)\.([^\.]+)$#', $name, $matches)) {
            $template = new TemplateReference($matches[1], $matches[2], $matches[3], $matches[4], $matches[5]);
        } elseif (preg_match('#^(@([^/]+)/)?(([^/]+)/)?(.+)\.([^\.]+)\.([^\.]+)$#', $name, $matches)) {
            if ($matches[2]) {
                $matches[2] .= 'Bundle';
            }

            $template = new TemplateReference($matches[2], $matches[4], $matches[5], $matches[6], $matches[7]);
        } else {
            throw new \InvalidArgumentException(sprintf('Template name "%s" is not valid (format is "bundle:section:template.format.engine").', $name));
        }

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
