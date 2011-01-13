<?php

namespace Symfony\Bundle\TwigBundle\Loader;

use Symfony\Component\Templating\TemplateNameParserInterface;
use Symfony\Component\HttpKernel\Log\LoggerInterface;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * FilesystemLoader extends the default Twig filesystem loader
 * to work with the Symfony2 paths.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class FilesystemLoader extends \Twig_Loader_Filesystem
{
    protected $nameParser;
    protected $logger;

    /**
     * Constructor.
     *
     * @param TemplateNameParserInterface $nameParser A TemplateNameParserInterface instance
     */
    public function __construct(TemplateNameParserInterface $nameParser, array $paths = array(), LoggerInterface $logger = null)
    {
        parent::__construct($paths);

        $this->nameParser = $nameParser;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function setPaths($paths)
    {
        // invalidate the cache
        $this->cache = array();

        // we don't check if the directory exists here as we have path patterns, not paths
        $this->paths = is_array($paths) ? $paths : array($paths);
    }

    protected function findTemplate($name)
    {
        list($tpl, $options) = $this->nameParser->parse($name);

        // normalize name
        $tpl = preg_replace('#/{2,}#', '/', strtr($tpl, '\\', '/'));

        if (isset($this->cache[$tpl])) {
            return $this->cache[$tpl];
        }

        $this->validateName($tpl);
        $this->validateName($options['bundle']);
        $this->validateName($options['controller']);
        $this->validateName($options['format']);

        $options['name'] = $tpl;

        $replacements = array();
        foreach ($options as $key => $value) {
            $replacements['%'.$key.'%'] = $value;
        }

        $logs = array();
        foreach ($this->paths as $path) {
            if (is_file($file = strtr($path, $replacements))) {
                if (null !== $this->logger) {
                    $this->logger->info(sprintf('Loaded template file "%s"', $file));
                }

                return $file;
            }

            if (null !== $this->logger) {
                $logs[] = sprintf('Failed loading template file "%s"', $file);
            }
        }

        if (null !== $this->logger) {
            foreach ($logs as $log) {
                $this->logger->debug($log);
            }
        }

        throw new \Twig_Error_Loader(sprintf('Unable to find template "%s".', $name));
    }
}
