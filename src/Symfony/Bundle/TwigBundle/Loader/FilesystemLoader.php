<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Loader;

use Symfony\Component\Templating\TemplateNameParserInterface;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Templating\TemplateReferenceInterface;

/**
 * FilesystemLoader extends the default Twig filesystem loader
 * to work with the Symfony2 paths.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class FilesystemLoader extends \Twig_Loader_Filesystem
{
    protected $locator;
    protected $parser;
    protected $cache;

    /**
     * Constructor.
     *
     * @param FileLocatorInterface        $locator A FileLocatorInterface instance
     * @param TemplateNameParserInterface $parser  A TemplateNameParserInterface instance
     */
    public function __construct(FileLocatorInterface $locator, TemplateNameParserInterface $parser)
    {
        $this->locator = $locator;
        $this->parser = $parser;
        $this->cache = array();
    }

    /**
     * Returns the path to the template file
     *
     * @param $name The template logical name
     *
     * @return string The path to the template file
     */
    protected function findTemplate($name)
    {
        try {
            $tpl = $this->parser->parse($name);
        } catch (\Exception $e) {
            return parent::findTemplate($name);
        }

        if (isset($this->cache[$key = $tpl->getLogicalName()])) {
            return $this->cache[$key];
        }

        $file = null;
        $previous = null;
        try {
            $file = $this->locator->locate($tpl);
        } catch (\InvalidArgumentException $e) {
            $previous = $e;
        }

        if (false === $file || null === $file) {
            throw new \Twig_Error_Loader(sprintf('Unable to find template "%s".', $tpl), -1, null, $previous);
        }

        return $this->cache[$key] = $file;
    }
}
