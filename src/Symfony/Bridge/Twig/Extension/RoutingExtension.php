<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Provides integration of the Routing component with Twig.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RoutingExtension extends \Twig_Extension
{
    private $generator;

    public function __construct(UrlGeneratorInterface $generator)
    {
        $this->generator = $generator;
    }

    /**
     * Returns a list of functions to add to the existing list.
     *
     * @return array An array of functions
     */
    public function getFunctions()
    {
        return array(
            'url'  => new \Twig_Function_Method($this, 'getUrl'),
            'path' => new \Twig_Function_Method($this, 'getPath'),
        );
    }

    public function getPath($name, $parameters = array(), $relative = false)
    {
        return $this->generator->generate($name, $parameters, $relative ? UrlGeneratorInterface::RELATIVE_PATH : UrlGeneratorInterface::ABSOLUTE_PATH);
    }

    public function getUrl($name, $parameters = array(), $schemeRelative = false)
    {
        return $this->generator->generate($name, $parameters, $schemeRelative ? UrlGeneratorInterface::NETWORK_PATH : UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'routing';
    }
}
