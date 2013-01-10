<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\RenderingStrategy;

use Symfony\Component\HttpKernel\Controller\ControllerReference;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Adds the possibility to generate a proxy URI for a given Controller.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class GeneratorAwareRenderingStrategy implements RenderingStrategyInterface
{
    protected $generator;

    /**
     * Sets a URL generator to use for proxy URIs generation.
     *
     * @param UrlGeneratorInterface $generator An UrlGeneratorInterface instance
     */
    public function setUrlGenerator(UrlGeneratorInterface $generator)
    {
        $this->generator = $generator;
    }

    /**
     * Generates a proxy URI for a given controller.
     *
     * This method only works when using the Symfony Routing component and
     * if a "_proxy" route is defined with a {_controller} and {_format}
     * placeholders.
     *
     * @param ControllerReference  $reference A ControllerReference instance
     * @param Request              $request    A Request instance
     *
     * @return string A proxy URI
     */
    protected function generateProxyUri(ControllerReference $reference, Request $request = null)
    {
        if (null === $this->generator) {
            throw new \LogicException('Unable to generate a proxy URL as there is no registered route generator.');
        }

        if (isset($reference->attributes['_format'])) {
            $format = $reference->attributes['_format'];
            unset($reference->attributes['_format']);
        } elseif (null !== $request) {
            $format = $request->getRequestFormat();
        } else {
            $format = 'html';
        }

        try {
            $uri = $this->generator->generate('_proxy', array('_controller' => $reference->controller, '_format' => $format), true);
        } catch (RouteNotFoundException $e) {
            throw new \LogicException('Unable to generate a proxy URL as the "_proxy" route is not registered.', 0, $e);
        }

        if ($path = http_build_query($reference->attributes, '', '&')) {
            $reference->query['path'] = $path;
        }

        if ($qs = http_build_query($reference->query, '', '&')) {
            $uri .= '?'.$qs;
        }

        return $uri;
    }
}
