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
        } elseif ('/' !== $pathinfo) {
            $pathinfo = '/' !== $pathinfo[-1] ? $pathinfo.'/' : substr($pathinfo, 0, -1);
            if ($ret = $this->doMatch($pathinfo, $allow, $allowSchemes)) {
                return $this->redirect($pathinfo, $ret['_route']) + $ret;
            }
            if ($allowSchemes) {
                goto redirect_scheme;
            }
        }

        throw new ResourceNotFoundException();
    }

    private function doMatch(string $rawPathinfo, array &$allow = array(), array &$allowSchemes = array()): ?array
    {
        $allow = $allowSchemes = array();
        $pathinfo = rawurldecode($rawPathinfo);
        $context = $this->context;
        $requestMethod = $canonicalMethod = $context->getMethod();

        if ('HEAD' === $requestMethod) {
            $canonicalMethod = 'GET';
        }

        switch ($pathinfo) {
            default:
                $routes = array(
                    '/trailing/simple/no-methods/' => array(array('_route' => 'simple_trailing_slash_no_methods'), null, null, null),
                    '/trailing/simple/get-method/' => array(array('_route' => 'simple_trailing_slash_GET_method'), null, array('GET' => 0), null),
                    '/trailing/simple/head-method/' => array(array('_route' => 'simple_trailing_slash_HEAD_method'), null, array('HEAD' => 0), null),
                    '/trailing/simple/post-method/' => array(array('_route' => 'simple_trailing_slash_POST_method'), null, array('POST' => 0), null),
                    '/not-trailing/simple/no-methods' => array(array('_route' => 'simple_not_trailing_slash_no_methods'), null, null, null),
                    '/not-trailing/simple/get-method' => array(array('_route' => 'simple_not_trailing_slash_GET_method'), null, array('GET' => 0), null),
                    '/not-trailing/simple/head-method' => array(array('_route' => 'simple_not_trailing_slash_HEAD_method'), null, array('HEAD' => 0), null),
                    '/not-trailing/simple/post-method' => array(array('_route' => 'simple_not_trailing_slash_POST_method'), null, array('POST' => 0), null),
                );

                if (!isset($routes[$pathinfo])) {
                    break;
                }
                list($ret, $requiredHost, $requiredMethods, $requiredSchemes) = $routes[$pathinfo];

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
                        .'|no\\-methods/([^/]++)/(*:47)'
                        .'|get\\-method/([^/]++)/(*:75)'
                        .'|head\\-method/([^/]++)/(*:104)'
                        .'|post\\-method/([^/]++)/(*:134)'
                    .')'
                    .'|/not\\-trailing/regex/(?'
                        .'|no\\-methods/([^/]++)(*:187)'
                        .'|get\\-method/([^/]++)(*:215)'
                        .'|head\\-method/([^/]++)(*:244)'
                        .'|post\\-method/([^/]++)(*:273)'
                    .')'
                .')$}sD',
        );

        foreach ($regexList as $offset => $regex) {
            while (preg_match($regex, $matchedPathinfo, $matches)) {
                switch ($m = (int) $matches['MARK']) {
                    default:
                        $routes = array(
                            47 => array(array('_route' => 'regex_trailing_slash_no_methods'), array('param'), null, null),
                            75 => array(array('_route' => 'regex_trailing_slash_GET_method'), array('param'), array('GET' => 0), null),
                            104 => array(array('_route' => 'regex_trailing_slash_HEAD_method'), array('param'), array('HEAD' => 0), null),
                            134 => array(array('_route' => 'regex_trailing_slash_POST_method'), array('param'), array('POST' => 0), null),
                            187 => array(array('_route' => 'regex_not_trailing_slash_no_methods'), array('param'), null, null),
                            215 => array(array('_route' => 'regex_not_trailing_slash_GET_method'), array('param'), array('GET' => 0), null),
                            244 => array(array('_route' => 'regex_not_trailing_slash_HEAD_method'), array('param'), array('HEAD' => 0), null),
                            273 => array(array('_route' => 'regex_not_trailing_slash_POST_method'), array('param'), array('POST' => 0), null),
                        );

                        list($ret, $vars, $requiredMethods, $requiredSchemes) = $routes[$m];

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

                if (273 === $m) {
                    break;
                }
                $regex = substr_replace($regex, 'F', $m - $offset, 1 + strlen($m));
                $offset += strlen($m);
            }
        }
        if ('/' === $pathinfo && !$allow && !$allowSchemes) {
            throw new Symfony\Component\Routing\Exception\NoConfigurationException();
        }

        return null;
    }
}
