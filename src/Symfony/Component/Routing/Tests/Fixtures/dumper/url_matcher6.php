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

    public function match($rawPathinfo)
    {
        $allow = $allowSchemes = array();
        $pathinfo = rawurldecode($rawPathinfo) ?: '/';
        $context = $this->context;
        $requestMethod = $canonicalMethod = $context->getMethod();

        if ('HEAD' === $requestMethod) {
            $canonicalMethod = 'GET';
        }

        switch ($trimmedPathinfo = '/' !== $pathinfo && '/' === $pathinfo[-1] ? substr($pathinfo, 0, -1) : $pathinfo) {
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

                if ('/' !== $pathinfo && $hasTrailingSlash !== ('/' === $pathinfo[-1])) {
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
                .')(?:/?)$}sD',
        );

        foreach ($regexList as $offset => $regex) {
            while (preg_match($regex, $matchedPathinfo, $matches)) {
                switch ($m = (int) $matches['MARK']) {
                    default:
                        $routes = array(
                            46 => array(array('_route' => 'regex_trailing_slash_no_methods'), array('param'), null, null, true),
                            73 => array(array('_route' => 'regex_trailing_slash_GET_method'), array('param'), array('GET' => 0), null, true),
                            101 => array(array('_route' => 'regex_trailing_slash_HEAD_method'), array('param'), array('HEAD' => 0), null, true),
                            130 => array(array('_route' => 'regex_trailing_slash_POST_method'), array('param'), array('POST' => 0), null, true),
                            183 => array(array('_route' => 'regex_not_trailing_slash_no_methods'), array('param'), null, null, false),
                            211 => array(array('_route' => 'regex_not_trailing_slash_GET_method'), array('param'), array('GET' => 0), null, false),
                            240 => array(array('_route' => 'regex_not_trailing_slash_HEAD_method'), array('param'), array('HEAD' => 0), null, false),
                            269 => array(array('_route' => 'regex_not_trailing_slash_POST_method'), array('param'), array('POST' => 0), null, false),
                        );

                        list($ret, $vars, $requiredMethods, $requiredSchemes, $hasTrailingSlash) = $routes[$m];

                        if ('/' !== $pathinfo && $hasTrailingSlash !== ('/' === $pathinfo[-1])) {
                            break;
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

        throw $allow ? new MethodNotAllowedException(array_keys($allow)) : new ResourceNotFoundException();
    }
}
