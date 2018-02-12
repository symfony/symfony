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

    public function match($rawPathinfo)
    {
        $allow = array();
        $pathinfo = rawurldecode($rawPathinfo);
        $trimmedPathinfo = rtrim($pathinfo, '/');
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
                    '/not-trailing/simple/no-methods' => array(array('_route' => 'simple_not_trailing_slash_no_methods'), null, null, null),
                    '/not-trailing/simple/get-method' => array(array('_route' => 'simple_not_trailing_slash_GET_method'), null, array('GET' => 0), null),
                    '/not-trailing/simple/head-method' => array(array('_route' => 'simple_not_trailing_slash_HEAD_method'), null, array('HEAD' => 0), null),
                    '/not-trailing/simple/post-method' => array(array('_route' => 'simple_not_trailing_slash_POST_method'), null, array('POST' => 0), null),
                );

                if (!isset($routes[$trimmedPathinfo])) {
                    break;
                }
                list($ret, $requiredHost, $requiredMethods, $requiredSchemes) = $routes[$trimmedPathinfo];

                if (empty($routes[$trimmedPathinfo][4]) || '/' === $pathinfo[-1]) {
                    // no-op
                } elseif ('GET' !== $canonicalMethod) {
                    $allow['GET'] = 'GET';
                    break;
                } else {
                    return array_replace($ret, $this->redirect($rawPathinfo.'/', $ret['_route']));
                }

                if ($requiredSchemes && !isset($requiredSchemes[$context->getScheme()])) {
                    if ('GET' !== $canonicalMethod) {
                        $allow['GET'] = 'GET';
                        break;
                    }

                    return array_replace($ret, $this->redirect($rawPathinfo, $ret['_route'], key($requiredSchemes)));
                }

                if ($requiredMethods && !isset($requiredMethods[$canonicalMethod]) && !isset($requiredMethods[$requestMethod])) {
                    $allow += $requiredMethods;
                    break;
                }

                return $ret;
        }

        $matchedPathinfo = $pathinfo;
        $regexList = array(
            0 => '{^(?'
                    .'|/trailing/regex(?'
                        .'|/no\\-methods/([^/]++)/?(*:48)'
                        .'|/get\\-method/([^/]++)/?(*:78)'
                        .'|/head\\-method/([^/]++)/(*:108)'
                        .'|/post\\-method/([^/]++)/(*:139)'
                    .')'
                    .'|/not\\-trailing/regex(?'
                        .'|/no\\-methods/([^/]++)(*:192)'
                        .'|/get\\-method/([^/]++)(*:221)'
                        .'|/head\\-method/([^/]++)(*:251)'
                        .'|/post\\-method/([^/]++)(*:281)'
                    .')'
                .')$}sD',
        );

        foreach ($regexList as $offset => $regex) {
            while (preg_match($regex, $matchedPathinfo, $matches)) {
                switch ($m = (int) $matches['MARK']) {
                    default:
                        $routes = array(
                            48 => array(array('_route' => 'regex_trailing_slash_no_methods'), array('param'), null, null, true),
                            78 => array(array('_route' => 'regex_trailing_slash_GET_method'), array('param'), array('GET' => 0), null, true),
                            108 => array(array('_route' => 'regex_trailing_slash_HEAD_method'), array('param'), array('HEAD' => 0), null),
                            139 => array(array('_route' => 'regex_trailing_slash_POST_method'), array('param'), array('POST' => 0), null),
                            192 => array(array('_route' => 'regex_not_trailing_slash_no_methods'), array('param'), null, null),
                            221 => array(array('_route' => 'regex_not_trailing_slash_GET_method'), array('param'), array('GET' => 0), null),
                            251 => array(array('_route' => 'regex_not_trailing_slash_HEAD_method'), array('param'), array('HEAD' => 0), null),
                            281 => array(array('_route' => 'regex_not_trailing_slash_POST_method'), array('param'), array('POST' => 0), null),
                        );

                        list($ret, $vars, $requiredMethods, $requiredSchemes) = $routes[$m];

                        foreach ($vars as $i => $v) {
                            if (isset($matches[1 + $i])) {
                                $ret[$v] = $matches[1 + $i];
                            }
                        }

                        if (empty($routes[$m][4]) || '/' === $pathinfo[-1]) {
                            // no-op
                        } elseif ('GET' !== $canonicalMethod) {
                            $allow['GET'] = 'GET';
                            break;
                        } else {
                            return array_replace($ret, $this->redirect($rawPathinfo.'/', $ret['_route']));
                        }

                        if ($requiredSchemes && !isset($requiredSchemes[$context->getScheme()])) {
                            if ('GET' !== $canonicalMethod) {
                                $allow['GET'] = 'GET';
                                break;
                            }

                            return array_replace($ret, $this->redirect($rawPathinfo, $ret['_route'], key($requiredSchemes)));
                        }

                        if ($requiredMethods && !isset($requiredMethods[$canonicalMethod]) && !isset($requiredMethods[$requestMethod])) {
                            $allow += $requiredMethods;
                            break;
                        }

                        return $ret;
                }

                if (281 === $m) {
                    break;
                }
                $regex = substr_replace($regex, 'F', $m - $offset, 1 + strlen($m));
                $offset += strlen($m);
            }
        }

        throw $allow ? new MethodNotAllowedException(array_keys($allow)) : new ResourceNotFoundException();
    }
}
