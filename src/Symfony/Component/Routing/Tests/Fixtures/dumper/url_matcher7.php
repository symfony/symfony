<?php

use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RequestContext;

/**
 * This class has been auto-generated
 * by the Symfony Routing Component.
 */
class ProjectUrlMatcher extends Symfony\Component\Routing\Tests\Fixtures\RedirectableUrlMatcher
{
    public function __construct(RequestContext $context)
    {
        $this->context = $context;
    }

    public function match($pathinfo)
    {
        $allow = $allowSchemes = array();
        if ($ret = $this->doMatch($pathinfo, $allow, $allowSchemes)) {
            return $ret;
        }
        if ($allow) {
            throw new MethodNotAllowedException(array_keys($allow));
        }
        if (!in_array($this->context->getMethod(), array('HEAD', 'GET'), true)) {
            // no-op
        } elseif ($allowSchemes) {
            redirect_scheme:
            $scheme = $this->context->getScheme();
            $this->context->setScheme(key($allowSchemes));
            try {
                if ($ret = $this->doMatch($pathinfo)) {
                    return $this->redirect($pathinfo, $ret['_route'], $this->context->getScheme()) + $ret;
                }
            } finally {
                $this->context->setScheme($scheme);
            }
        } elseif ('/' !== $trimmedPathinfo = rtrim($pathinfo, '/') ?: '/') {
            $pathinfo = $trimmedPathinfo === $pathinfo ? $pathinfo.'/' : $trimmedPathinfo;
            if ($ret = $this->doMatch($pathinfo, $allow, $allowSchemes)) {
                return $this->redirect($pathinfo, $ret['_route']) + $ret;
            }
            if ($allowSchemes) {
                goto redirect_scheme;
            }
        }

        throw new ResourceNotFoundException();
    }

    private function doMatch(string $pathinfo, array &$allow = array(), array &$allowSchemes = array()): array
    {
        $allow = $allowSchemes = array();
        $pathinfo = rawurldecode($pathinfo) ?: '/';
        $trimmedPathinfo = rtrim($pathinfo, '/') ?: '/';
        $context = $this->context;
        $requestMethod = $canonicalMethod = $context->getMethod();

        if ('HEAD' === $requestMethod) {
            $canonicalMethod = 'GET';
        }

        switch ($trimmedPathinfo) {
            default:
                $routes = array(
                    '/trailing/simple/no-methods' => array(array('_route' => 'simple_trailing_slash_no_methods'), null, null, null, true),
                    '/trailing/simple/get-method' => array(array('_route' => 'simple_trailing_slash_GET_method'), null, array('GET' => 0), null, true),
                    '/trailing/simple/head-method' => array(array('_route' => 'simple_trailing_slash_HEAD_method'), null, array('HEAD' => 0), null, true),
                    '/trailing/simple/post-method' => array(array('_route' => 'simple_trailing_slash_POST_method'), null, array('POST' => 0), null, true),
                    '/not-trailing/simple/no-methods' => array(array('_route' => 'simple_not_trailing_slash_no_methods'), null, null, null, false),
                    '/not-trailing/simple/get-method' => array(array('_route' => 'simple_not_trailing_slash_GET_method'), null, array('GET' => 0), null, false),
                    '/not-trailing/simple/head-method' => array(array('_route' => 'simple_not_trailing_slash_HEAD_method'), null, array('HEAD' => 0), null, false),
                    '/not-trailing/simple/post-method' => array(array('_route' => 'simple_not_trailing_slash_POST_method'), null, array('POST' => 0), null, false),
                );

                if (!isset($routes[$trimmedPathinfo])) {
                    break;
                }
                list($ret, $requiredHost, $requiredMethods, $requiredSchemes, $hasTrailingSlash) = $routes[$trimmedPathinfo];
                if ('/' !== $pathinfo && $hasTrailingSlash === ($trimmedPathinfo === $pathinfo)) {
                    if ('GET' === $canonicalMethod && (!$requiredMethods || isset($requiredMethods['GET']))) {
                        return $allow = $allowSchemes = array();
                    }
                    break;
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

        $matchedPathinfo = $pathinfo;
        $regexList = array(
            0 => '{^(?'
                    .'|/trailing/regex/(?'
                        .'|no\\-methods/([^/]++)(*:46)'
                        .'|get\\-method/([^/]++)(*:73)'
                        .'|head\\-method/([^/]++)(*:101)'
                        .'|post\\-method/([^/]++)(*:130)'
                    .')'
                    .'|/not\\-trailing/regex/(?'
                        .'|no\\-methods/([^/]++)(*:183)'
                        .'|get\\-method/([^/]++)(*:211)'
                        .'|head\\-method/([^/]++)(*:240)'
                        .'|post\\-method/([^/]++)(*:269)'
                    .')'
                .')/?$}sD',
        );

        foreach ($regexList as $offset => $regex) {
            while (preg_match($regex, $matchedPathinfo, $matches)) {
                switch ($m = (int) $matches['MARK']) {
                    default:
                        $routes = array(
                            46 => array(array('_route' => 'regex_trailing_slash_no_methods'), array('param'), null, null, true, true),
                            73 => array(array('_route' => 'regex_trailing_slash_GET_method'), array('param'), array('GET' => 0), null, true, true),
                            101 => array(array('_route' => 'regex_trailing_slash_HEAD_method'), array('param'), array('HEAD' => 0), null, true, true),
                            130 => array(array('_route' => 'regex_trailing_slash_POST_method'), array('param'), array('POST' => 0), null, true, true),
                            183 => array(array('_route' => 'regex_not_trailing_slash_no_methods'), array('param'), null, null, false, true),
                            211 => array(array('_route' => 'regex_not_trailing_slash_GET_method'), array('param'), array('GET' => 0), null, false, true),
                            240 => array(array('_route' => 'regex_not_trailing_slash_HEAD_method'), array('param'), array('HEAD' => 0), null, false, true),
                            269 => array(array('_route' => 'regex_not_trailing_slash_POST_method'), array('param'), array('POST' => 0), null, false, true),
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
                            if ('GET' === $canonicalMethod && (!$requiredMethods || isset($requiredMethods['GET']))) {
                                return $allow = $allowSchemes = array();
                            }
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

                if (269 === $m) {
                    break;
                }
                $regex = substr_replace($regex, 'F', $m - $offset, 1 + strlen($m));
                $offset += strlen($m);
            }
        }
        if ('/' === $pathinfo && !$allow && !$allowSchemes) {
            throw new Symfony\Component\Routing\Exception\NoConfigurationException();
        }

        return array();
    }
}
