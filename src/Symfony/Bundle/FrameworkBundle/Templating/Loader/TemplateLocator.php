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

use Symfony\Component\Templating\Loader\TemplateNameParserInterface;
use Symfony\Component\HttpKernel\Kernel;

/**
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class TemplateLocator implements TemplateLocatorInterface
{
    protected $kernel;
    protected $parser;
    protected $path;
    protected $cache;

    /**
     * Constructor.
     *
     * @param Kernel                      $kernel A Kernel instance
     * @param TemplateNameParserInterface $parser A TemplateNameParserInterface instance
     * @param string                      $path   A global fallback path
     */
    public function __construct(Kernel $kernel, TemplateNameParserInterface $parser, $path)
    {
        $this->kernel = $kernel;
        $this->path = $path;
        $this->parser = $parser;
        $this->cache = array();
    }

    public function locate($name)
    {
        // normalize name
        $name = str_replace(':/' , ':', preg_replace('#/{2,}#', '/', strtr($name, '\\', '/')));

        if (isset($this->cache[$name])) {
            return $this->cache[$name];
        }

        if (false !== strpos($name, '..')) {
            throw new \RuntimeException(sprintf('Template name "%s" contains invalid characters.', $name));
        }

        $parameters = $this->parser->parse($name);
        $resource = $parameters['bundle'].'/Resources/views/'.$parameters['controller'].'/'.$parameters['name'].'.'.$parameters['format'].'.'.$parameters['renderer'];

        if (!$parameters['bundle']) {
            if (is_file($file = $this->path.'/views/'.$parameters['controller'].'/'.$parameters['name'].'.'.$parameters['format'].'.'.$parameters['renderer'])) {
                return $this->cache[$name] = $file;
            }

            throw new \InvalidArgumentException(sprintf('Unable to find template "%s" in "%s".', $name, $this->path));
        }

        try {
            return $this->kernel->locateResource('@'.$resource, $this->path);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(sprintf('Unable to find template "%s".', $name, $this->path), 0, $e);
        }
    }
}
