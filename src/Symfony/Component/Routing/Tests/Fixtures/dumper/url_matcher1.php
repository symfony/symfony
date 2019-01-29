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
        $allow = $allowSchemes = [];
        $pathinfo = rawurldecode($pathinfo) ?: '/';
        $trimmedPathinfo = rtrim($pathinfo, '/') ?: '/';
        $context = $this->context;
        $requestMethod = $canonicalMethod = $context->getMethod();
        $host = strtolower($context->getHost());

        if ('HEAD' === $requestMethod) {
            $canonicalMethod = 'GET';
        }

        switch ($trimmedPathinfo) {
            default:
                $routes = [
                    '/test/baz' => [['_route' => 'baz'], null, null, null, false],
                    '/test/baz.html' => [['_route' => 'baz2'], null, null, null, false],
                    '/test/baz3' => [['_route' => 'baz3'], null, null, null, true],
                    '/foofoo' => [['_route' => 'foofoo', 'def' => 'test'], null, null, null, false],
                    '/spa ce' => [['_route' => 'space'], null, null, null, false],
                    '/multi/new' => [['_route' => 'overridden2'], null, null, null, false],
                    '/multi/hey' => [['_route' => 'hey'], null, null, null, true],
                    '/ababa' => [['_route' => 'ababa'], null, null, null, false],
                    '/route1' => [['_route' => 'route1'], 'a.example.com', null, null, false],
                    '/c2/route2' => [['_route' => 'route2'], 'a.example.com', null, null, false],
                    '/route4' => [['_route' => 'route4'], 'a.example.com', null, null, false],
                    '/c2/route3' => [['_route' => 'route3'], 'b.example.com', null, null, false],
                    '/route5' => [['_route' => 'route5'], 'c.example.com', null, null, false],
                    '/route6' => [['_route' => 'route6'], null, null, null, false],
                    '/route11' => [['_route' => 'route11'], '#^(?P<var1>[^\\.]++)\\.example\\.com$#sDi', null, null, false],
                    '/route12' => [['_route' => 'route12', 'var1' => 'val'], '#^(?P<var1>[^\\.]++)\\.example\\.com$#sDi', null, null, false],
                    '/route17' => [['_route' => 'route17'], null, null, null, false],
                ];

                if (!isset($routes[$trimmedPathinfo])) {
                    break;
                }
                list($ret, $requiredHost, $requiredMethods, $requiredSchemes, $hasTrailingSlash) = $routes[$trimmedPathinfo];
                if ('/' !== $pathinfo && $hasTrailingSlash === ($trimmedPathinfo === $pathinfo)) {
                    break;
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
        $regexList = [
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
                .')/?$}sD',
        ];

        foreach ($regexList as $offset => $regex) {
            while (preg_match($regex, $matchedPathinfo, $matches)) {
                switch ($m = (int) $matches['MARK']) {
                    case 115:
                        // baz4
                        if ('/' !== $pathinfo && $trimmedPathinfo === $pathinfo) {
                            goto not_baz4;
                        }
                        if ('/' !== $pathinfo && preg_match($regex, rtrim($matchedPathinfo, '/') ?: '/', $n) && $m === (int) $n['MARK']) {
                            $matches = $n;
                        }

                        $matches = ['foo' => $matches[1] ?? null];

                        return $this->mergeDefaults(['_route' => 'baz4'] + $matches, []);
                        not_baz4:

                        // baz5
                        if ('/' !== $pathinfo && $trimmedPathinfo === $pathinfo) {
                            goto not_baz5;
                        }
                        if ('/' !== $pathinfo && preg_match($regex, rtrim($matchedPathinfo, '/') ?: '/', $n) && $m === (int) $n['MARK']) {
                            $matches = $n;
                        }

                        $ret = $this->mergeDefaults(['_route' => 'baz5'] + $matches, []);
                        if (!isset(($a = ['POST' => 0])[$requestMethod])) {
                            $allow += $a;
                            goto not_baz5;
                        }

                        return $ret;
                        not_baz5:

                        // baz.baz6
                        if ('/' !== $pathinfo && $trimmedPathinfo === $pathinfo) {
                            goto not_bazbaz6;
                        }
                        if ('/' !== $pathinfo && preg_match($regex, rtrim($matchedPathinfo, '/') ?: '/', $n) && $m === (int) $n['MARK']) {
                            $matches = $n;
                        }

                        $ret = $this->mergeDefaults(['_route' => 'baz.baz6'] + $matches, []);
                        if (!isset(($a = ['PUT' => 0])[$requestMethod])) {
                            $allow += $a;
                            goto not_bazbaz6;
                        }

                        return $ret;
                        not_bazbaz6:

                        break;
                    case 160:
                        // foo1
                        if ($trimmedPathinfo !== $pathinfo) {
                            goto not_foo1;
                        }

                        $matches = ['foo' => $matches[1] ?? null];

                        $ret = $this->mergeDefaults(['_route' => 'foo1'] + $matches, []);
                        if (!isset(($a = ['PUT' => 0])[$requestMethod])) {
                            $allow += $a;
                            goto not_foo1;
                        }

                        return $ret;
                        not_foo1:

                        break;
                    case 204:
                        // foo2
                        if ($trimmedPathinfo !== $pathinfo) {
                            goto not_foo2;
                        }

                        $matches = ['foo1' => $matches[1] ?? null];

                        return $this->mergeDefaults(['_route' => 'foo2'] + $matches, []);
                        not_foo2:

                        break;
                    case 279:
                        // foo3
                        if ($trimmedPathinfo !== $pathinfo) {
                            goto not_foo3;
                        }

                        $matches = ['_locale' => $matches[1] ?? null, 'foo' => $matches[2] ?? null];

                        return $this->mergeDefaults(['_route' => 'foo3'] + $matches, []);
                        not_foo3:

                        break;
                    default:
                        $routes = [
                            47 => [['_route' => 'foo', 'def' => 'test'], ['bar'], null, null, false, true],
                            70 => [['_route' => 'bar'], ['foo'], ['GET' => 0, 'HEAD' => 1], null, false, true],
                            90 => [['_route' => 'barhead'], ['foo'], ['GET' => 0], null, false, true],
                            131 => [['_route' => 'quoter'], ['quoter'], null, null, false, true],
                            168 => [['_route' => 'bar1'], ['bar'], null, null, false, true],
                            181 => [['_route' => 'overridden'], ['var'], null, null, false, true],
                            212 => [['_route' => 'bar2'], ['bar1'], null, null, false, true],
                            248 => [['_route' => 'helloWorld', 'who' => 'World!'], ['who'], null, null, false, true],
                            287 => [['_route' => 'bar3'], ['_locale', 'bar'], null, null, false, true],
                            309 => [['_route' => 'foo4'], ['foo'], null, null, false, true],
                            371 => [['_route' => 'route13'], ['var1', 'name'], null, null, false, true],
                            389 => [['_route' => 'route14', 'var1' => 'val'], ['var1', 'name'], null, null, false, true],
                            441 => [['_route' => 'route15'], ['name'], null, null, false, true],
                            489 => [['_route' => 'route16', 'var1' => 'val'], ['name'], null, null, false, true],
                            510 => [['_route' => 'a'], [], null, null, false, false],
                            531 => [['_route' => 'b'], ['var'], null, null, false, true],
                            549 => [['_route' => 'c'], ['var'], null, null, false, true],
                        ];

                        list($ret, $vars, $requiredMethods, $requiredSchemes, $hasTrailingSlash, $hasTrailingVar) = $routes[$m];

                        $hasTrailingVar = $trimmedPathinfo !== $pathinfo && $hasTrailingVar;
                        if ('/' !== $pathinfo && !$hasTrailingVar && $hasTrailingSlash === ($trimmedPathinfo === $pathinfo)) {
                            break;
                        }
                        if ($hasTrailingSlash && $hasTrailingVar && preg_match($regex, rtrim($matchedPathinfo, '/') ?: '/', $n) && $m === (int) $n['MARK']) {
                            $matches = $n;
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

        throw $allow ? new MethodNotAllowedException(array_keys($allow)) : new ResourceNotFoundException();
    }
}
