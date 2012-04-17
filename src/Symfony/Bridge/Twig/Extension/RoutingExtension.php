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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Provides integration of the Routing component with Twig.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RoutingExtension extends \Twig_Extension
{
    /**
     * @var UrlGeneratorInterface
     */
    private $generator;

    /**
     * @var Request|null
     */
    private $request;

    /**
     * Constructor.
     *
     * @param UrlGeneratorInterface $generator A UrlGeneratorInterface instance
     */
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
            new \Twig_SimpleFunction('url', array($this, 'getUrl'), array('is_safe_callback' => array($this, 'isUrlGenerationSafe'))),
            new \Twig_SimpleFunction('path', array($this, 'getPath'), array('is_safe_callback' => array($this, 'isUrlGenerationSafe'))),
            new \Twig_SimpleFunction('subpath', array($this, 'getSubPath')),
        );
    }

    public function getUrl($name, $parameters = array(), $schemeRelative = false)
    {
        return $this->generator->generate($name, $parameters, $schemeRelative ? UrlGeneratorInterface::NETWORK_PATH : UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function getPath($name, $parameters = array(), $relative = false)
    {
        return $this->generator->generate($name, $parameters, $relative ? UrlGeneratorInterface::RELATIVE_PATH : UrlGeneratorInterface::ABSOLUTE_PATH);
    }

    /**
     * Sets the current request that is needed for the subpath function.
     *
     * @param Request|null $request
     */
    public function setRequest(Request $request = null)
    {
        $this->request = $request;
    }

    /**
     * Returns the relative path to a route (defaults to current route) with all current route parameters merged
     * with the passed params. 
     *
     * Optionally one can also include the params of the query string. It's also possible to remove existing
     * params by passing null as value of a specific parameter. The path is a relative path to the
     * target URL, e.g. "../slug", based on the current request path.
     *
     * @param string  $name         The route name (when empty it defaults to the current route)
     * @param array   $parameters   The parameters that should be added or overwrite existing params
     * @param Boolean $includeQuery Whether the current params in the query string should be included (disabled by default)
     *
     * @return string The path to the route with given parameters
     *
     * @throws \LogicException when the request is not set
     */
    public function getSubPath($name = '', array $parameters = array(), $includeQuery = false)
    {
        if (null === $this->request) {
            throw new \LogicException('The subpath function needs the current request to be set via setRequest().');
        }

        $parameters = array_replace(
            $includeQuery ? $this->request->query->all() : array(),
            $this->request->attributes->get('_route_params', array()),
            $parameters
        );
        $parameters = array_filter($parameters, function ($value) { 
            return null !== $value; 
        });

        return $this->generator->generate($name ?: $this->request->attributes->get('_route', ''), $parameters, UrlGeneratorInterface::RELATIVE_PATH);
    }

    /**
     * Determines at compile time whether the generated URL will be safe and thus
     * saving the unneeded automatic escaping for performance reasons.
     *
     * The URL generation process percent encodes non-alphanumeric characters. So there is no risk
     * that malicious/invalid characters are part of the URL. The only character within an URL that
     * must be escaped in html is the ampersand ("&") which separates query params. So we cannot mark
     * the URL generation as always safe, but only when we are sure there won't be multiple query
     * params. This is the case when there are none or only one constant parameter given.
     * E.g. we know beforehand this will be safe:
     * - path('route')
     * - path('route', {'param': 'value'})
     * But the following may not:
     * - path('route', var)
     * - path('route', {'param': ['val1', 'val2'] }) // a sub-array
     * - path('route', {'param1': 'value1', 'param2': 'value2'})
     * If param1 and param2 reference placeholder in the route, it would still be safe. But we don't know.
     *
     * @param \Twig_Node $argsNode The arguments of the path/url function
     *
     * @return array An array with the contexts the URL is safe
     */
    public function isUrlGenerationSafe(\Twig_Node $argsNode)
    {
        // support named arguments
        $paramsNode = $argsNode->hasNode('parameters') ? $argsNode->getNode('parameters') : (
            $argsNode->hasNode(1) ? $argsNode->getNode(1) : null
        );

        if (null === $paramsNode || $paramsNode instanceof \Twig_Node_Expression_Array && count($paramsNode) <= 2 &&
            (!$paramsNode->hasNode(1) || $paramsNode->getNode(1) instanceof \Twig_Node_Expression_Constant)
        ) {
            return array('html');
        }

        return array();
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
