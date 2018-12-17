<?php

use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RequestContext;

/**
 * This class has been auto-generated
 * by the Symfony Routing Component.
 */
class ProjectUrlMatcher extends Symfony\Component\Routing\Matcher\UrlMatcher
{
    public function __construct(RequestContext $context)
    {
        $this->context = $context;
    }

    public function match($pathinfo)
    {
        $allow = $allowSchemes = array();
        $pathinfo = rawurldecode($pathinfo) ?: '/';
        $trimmedPathinfo = rtrim($pathinfo, '/') ?: '/';
        $context = $this->context;
        $requestMethod = $canonicalMethod = $context->getMethod();

        if ('HEAD' === $requestMethod) {
            $canonicalMethod = 'GET';
        }

        $matchedPathinfo = $pathinfo;
        $regexList = array(
            0 => '{^(?'
                    .'|/abc([^/]++)/(?'
                        .'|1(?'
                            .'|(*:27)'
                            .'|0(?'
                                .'|(*:38)'
                                .'|0(*:46)'
                            .')'
                        .')'
                        .'|2(?'
                            .'|(*:59)'
                            .'|0(?'
                                .'|(*:70)'
                                .'|0(*:78)'
                            .')'
                        .')'
                    .')'
                .')/?$}sD',
        );

        foreach ($regexList as $offset => $regex) {
            while (preg_match($regex, $matchedPathinfo, $matches)) {
                switch ($m = (int) $matches['MARK']) {
                    default:
                        $routes = array(
                            27 => array(array('_route' => 'r1'), array('foo'), null, null, false, false),
                            38 => array(array('_route' => 'r10'), array('foo'), null, null, false, false),
                            46 => array(array('_route' => 'r100'), array('foo'), null, null, false, false),
                            59 => array(array('_route' => 'r2'), array('foo'), null, null, false, false),
                            70 => array(array('_route' => 'r20'), array('foo'), null, null, false, false),
                            78 => array(array('_route' => 'r200'), array('foo'), null, null, false, false),
                        );

                        list($ret, $vars, $requiredMethods, $requiredSchemes, $hasTrailingSlash, $hasTrailingVar) = $routes[$m];

                        if ($trimmedPathinfo === $pathinfo || !$hasTrailingVar) {
                            // no-op
                        } elseif (preg_match($regex, rtrim($matchedPathinfo, '/') ?: '/', $n) && $m === (int) $n['MARK']) {
                            $matches = $n;
                        } else {
                            $hasTrailingSlash = true;
                        }
                        if ('/' !== $pathinfo && $hasTrailingSlash === ($trimmedPathinfo === $pathinfo)) {
                            if ($trimmedPathinfo === $pathinfo || !$hasTrailingVar) {
                                break;
                            }
                        }

                        foreach ($vars as $i => $v) {
                            if (isset($matches[1 + $i])) {
                                $ret[$v] = $matches[1 + $i];
                            }
                        }

                        $hasRequiredScheme = !$requiredSchemes || isset($requiredSchemes[$context->getScheme()]);
                        if ($requiredMethods && !isset($requiredMethods[$canonicalMethod]) && !isset($requiredMethods[$requestMethod])) {
                            if ($hasRequiredScheme) {
                                $allow += $requiredMethods;
                            }
                            break;
                        }
                        if (!$hasRequiredScheme) {
                            $allowSchemes += $requiredSchemes;
                            break;
                        }

                        return $ret;
                }

                if (78 === $m) {
                    break;
                }
                $regex = substr_replace($regex, 'F', $m - $offset, 1 + strlen($m));
                $offset += strlen($m);
            }
        }
        if ('/' === $pathinfo && !$allow && !$allowSchemes) {
            throw new Symfony\Component\Routing\Exception\NoConfigurationException();
        }

        throw $allow ? new MethodNotAllowedException(array_keys($allow)) : new ResourceNotFoundException();
    }
}
