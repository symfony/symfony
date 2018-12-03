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

    private function doMatch(string $rawPathinfo, array &$allow = array(), array &$allowSchemes = array()): array
    {
        $allow = $allowSchemes = array();
        $pathinfo = rawurldecode($rawPathinfo) ?: '/';
        $context = $this->context;
        $requestMethod = $canonicalMethod = $context->getMethod();
        $host = strtolower($context->getHost());

        if ('HEAD' === $requestMethod) {
            $canonicalMethod = 'GET';
        }

        switch ($trimmedPathinfo = '/' !== $pathinfo && '/' === $pathinfo[-1] ? substr($pathinfo, 0, -1) : $pathinfo) {
            default:
                $routes = array(
                    '/test/baz' => array(array('_route' => 'baz'), null, null, null, false),
                    '/test/baz.html' => array(array('_route' => 'baz2'), null, null, null, false),
                    '/test/baz3' => array(array('_route' => 'baz3'), null, null, null, true),
                    '/foofoo' => array(array('_route' => 'foofoo', 'def' => 'test'), null, null, null, false),
                    '/spa ce' => array(array('_route' => 'space'), null, null, null, false),
                    '/multi/new' => array(array('_route' => 'overridden2'), null, null, null, false),
                    '/multi/hey' => array(array('_route' => 'hey'), null, null, null, true),
                    '/ababa' => array(array('_route' => 'ababa'), null, null, null, false),
                    '/route1' => array(array('_route' => 'route1'), 'a.example.com', null, null, false),
                    '/c2/route2' => array(array('_route' => 'route2'), 'a.example.com', null, null, false),
                    '/route4' => array(array('_route' => 'route4'), 'a.example.com', null, null, false),
                    '/c2/route3' => array(array('_route' => 'route3'), 'b.example.com', null, null, false),
                    '/route5' => array(array('_route' => 'route5'), 'c.example.com', null, null, false),
                    '/route6' => array(array('_route' => 'route6'), null, null, null, false),
                    '/route11' => array(array('_route' => 'route11'), '#^(?P<var1>[^\\.]++)\\.example\\.com$#sDi', null, null, false),
                    '/route12' => array(array('_route' => 'route12', 'var1' => 'val'), '#^(?P<var1>[^\\.]++)\\.example\\.com$#sDi', null, null, false),
                    '/route17' => array(array('_route' => 'route17'), null, null, null, false),
                    '/secure' => array(array('_route' => 'secure'), null, null, array('https' => 0), false),
                    '/nonsecure' => array(array('_route' => 'nonsecure'), null, null, array('http' => 0), false),
                );

                if (!isset($routes[$trimmedPathinfo])) {
                    break;
                }
                list($ret, $requiredHost, $requiredMethods, $requiredSchemes, $hasTrailingSlash) = $routes[$trimmedPathinfo];

                if ('/' !== $pathinfo) {
                    if ($hasTrailingSlash !== ('/' === $pathinfo[-1])) {
                        if ((!$requiredMethods || isset($requiredMethods['GET'])) && 'GET' === $canonicalMethod) {
                            return $allow = $allowSchemes = array();
                        }
                        break;
                    }
                }

                if ($requiredHost) {
                    if ('#' !== $requiredHost[0] ? $requiredHost !== $host : !preg_match($requiredHost, $host, $hostMatches)) {
                        break;
                    }
                    if ('#' === $requiredHost[0] && $hostMatches) {
                        $hostMatches['_route'] = $ret['_route'];
                        $ret = $this->mergeDefaults($hostMatches, $ret);
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

        $matchedPathinfo = $host.'.'.$pathinfo;
        $regexList = array(
            0 => '{^(?'
                .'|(?:(?:[^./]*+\\.)++)(?'
                    .'|/foo/(baz|symfony)(*:47)'
                    .'|/bar(?'
                        .'|/([^/]++)(*:70)'
                        .'|head/([^/]++)(*:90)'
                    .')'
                    .'|/test/([^/]++)(?'
                        .'|(*:115)'
                    .')'
                    .'|/([\']+)(*:131)'
                    .'|/a/(?'
                        .'|b\'b/([^/]++)(?'
                            .'|(*:160)'
                            .'|(*:168)'
                        .')'
                        .'|(.*)(*:181)'
                        .'|b\'b/([^/]++)(?'
                            .'|(*:204)'
                            .'|(*:212)'
                        .')'
                    .')'
                    .'|/multi/hello(?:/([^/]++))?(*:248)'
                    .'|/([^/]++)/b/([^/]++)(?'
                        .'|(*:279)'
                        .'|(*:287)'
                    .')'
                    .'|/aba/([^/]++)(*:309)'
                .')|(?i:([^\\.]++)\\.example\\.com)\\.(?'
                    .'|/route1(?'
                        .'|3/([^/]++)(*:371)'
                        .'|4/([^/]++)(*:389)'
                    .')'
                .')|(?i:c\\.example\\.com)\\.(?'
                    .'|/route15/([^/]++)(*:441)'
                .')|(?:(?:[^./]*+\\.)++)(?'
                    .'|/route16/([^/]++)(*:489)'
                    .'|/a/(?'
                        .'|a\\.\\.\\.(*:510)'
                        .'|b/(?'
                            .'|([^/]++)(*:531)'
                            .'|c/([^/]++)(*:549)'
                        .')'
                    .')'
                .')'
                .')(?:/?)$}sD',
        );

        foreach ($regexList as $offset => $regex) {
            while (preg_match($regex, $matchedPathinfo, $matches)) {
                switch ($m = (int) $matches['MARK']) {
                    case 115:
                        $matches = array('foo' => $matches[1] ?? null);

                        // baz4
                        if ('/' !== $pathinfo[-1]) {
                            if ('GET' === $canonicalMethod) {
                                return $allow = $allowSchemes = array();
                            }
                            goto not_baz4;
                        }
                        if ('/' !== $pathinfo && preg_match($regex, substr($pathinfo, 0, -1), $n) && $m === (int) $n['MARK']) {
                            $matches = $n;
                        }

                        return $this->mergeDefaults(array('_route' => 'baz4') + $matches, array());
                        not_baz4:

                        // baz5
                        if ('/' !== $pathinfo[-1]) {
                            goto not_baz5;
                        }
                        if ('/' !== $pathinfo && preg_match($regex, substr($pathinfo, 0, -1), $n) && $m === (int) $n['MARK']) {
                            $matches = $n;
                        }

                        $ret = $this->mergeDefaults(array('_route' => 'baz5') + $matches, array());
                        if (!isset(($a = array('POST' => 0))[$requestMethod])) {
                            $allow += $a;
                            goto not_baz5;
                        }

                        return $ret;
                        not_baz5:

                        // baz.baz6
                        if ('/' !== $pathinfo[-1]) {
                            goto not_bazbaz6;
                        }
                        if ('/' !== $pathinfo && preg_match($regex, substr($pathinfo, 0, -1), $n) && $m === (int) $n['MARK']) {
                            $matches = $n;
                        }

                        $ret = $this->mergeDefaults(array('_route' => 'baz.baz6') + $matches, array());
                        if (!isset(($a = array('PUT' => 0))[$requestMethod])) {
                            $allow += $a;
                            goto not_bazbaz6;
                        }

                        return $ret;
                        not_bazbaz6:

                        break;
                    case 160:
                        $matches = array('foo' => $matches[1] ?? null);

                        // foo1
                        if ('/' !== $pathinfo && '/' === $pathinfo[-1] && preg_match($regex, substr($pathinfo, 0, -1), $n) && $m === (int) $n['MARK']) {
                            goto not_foo1;
                        }

                        $ret = $this->mergeDefaults(array('_route' => 'foo1') + $matches, array());
                        if (!isset(($a = array('PUT' => 0))[$requestMethod])) {
                            $allow += $a;
                            goto not_foo1;
                        }

                        return $ret;
                        not_foo1:

                        break;
                    case 204:
                        $matches = array('foo1' => $matches[1] ?? null);

                        // foo2
                        if ('/' !== $pathinfo && '/' === $pathinfo[-1] && preg_match($regex, substr($pathinfo, 0, -1), $n) && $m === (int) $n['MARK']) {
                            if ('GET' === $canonicalMethod) {
                                return $allow = $allowSchemes = array();
                            }
                            goto not_foo2;
                        }

                        return $this->mergeDefaults(array('_route' => 'foo2') + $matches, array());
                        not_foo2:

                        break;
                    case 279:
                        $matches = array('_locale' => $matches[1] ?? null, 'foo' => $matches[2] ?? null);

                        // foo3
                        if ('/' !== $pathinfo && '/' === $pathinfo[-1] && preg_match($regex, substr($pathinfo, 0, -1), $n) && $m === (int) $n['MARK']) {
                            if ('GET' === $canonicalMethod) {
                                return $allow = $allowSchemes = array();
                            }
                            goto not_foo3;
                        }

                        return $this->mergeDefaults(array('_route' => 'foo3') + $matches, array());
                        not_foo3:

                        break;
                    default:
                        $routes = array(
                            47 => array(array('_route' => 'foo', 'def' => 'test'), array('bar'), null, null, false),
                            70 => array(array('_route' => 'bar'), array('foo'), array('GET' => 0, 'HEAD' => 1), null, false),
                            90 => array(array('_route' => 'barhead'), array('foo'), array('GET' => 0), null, false),
                            131 => array(array('_route' => 'quoter'), array('quoter'), null, null, false),
                            168 => array(array('_route' => 'bar1'), array('bar'), null, null, false),
                            181 => array(array('_route' => 'overridden'), array('var'), null, null, false),
                            212 => array(array('_route' => 'bar2'), array('bar1'), null, null, false),
                            248 => array(array('_route' => 'helloWorld', 'who' => 'World!'), array('who'), null, null, false),
                            287 => array(array('_route' => 'bar3'), array('_locale', 'bar'), null, null, false),
                            309 => array(array('_route' => 'foo4'), array('foo'), null, null, false),
                            371 => array(array('_route' => 'route13'), array('var1', 'name'), null, null, false),
                            389 => array(array('_route' => 'route14', 'var1' => 'val'), array('var1', 'name'), null, null, false),
                            441 => array(array('_route' => 'route15'), array('name'), null, null, false),
                            489 => array(array('_route' => 'route16', 'var1' => 'val'), array('name'), null, null, false),
                            510 => array(array('_route' => 'a'), array(), null, null, false),
                            531 => array(array('_route' => 'b'), array('var'), null, null, false),
                            549 => array(array('_route' => 'c'), array('var'), null, null, false),
                        );

                        list($ret, $vars, $requiredMethods, $requiredSchemes, $hasTrailingSlash) = $routes[$m];

                        if ('/' !== $pathinfo) {
                            if ('/' === $pathinfo[-1]) {
                                if (preg_match($regex, substr($pathinfo, 0, -1), $n) && $m === (int) $n['MARK']) {
                                    $matches = $n;
                                } else {
                                    $hasTrailingSlash = true;
                                }
                            }

                            if ($hasTrailingSlash !== ('/' === $pathinfo[-1])) {
                                if ((!$requiredMethods || isset($requiredMethods['GET'])) && 'GET' === $canonicalMethod) {
                                    return $allow = $allowSchemes = array();
                                }
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

                if (549 === $m) {
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
