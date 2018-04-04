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
        $pathinfo = rawurldecode($rawPathinfo);
        $context = $this->context;
        $requestMethod = $canonicalMethod = $context->getMethod();
        $host = strtolower($context->getHost());

        if ('HEAD' === $requestMethod) {
            $canonicalMethod = 'GET';
        }

        switch ($pathinfo) {
            default:
                $routes = array(
                    '/test/baz' => array(array('_route' => 'baz'), null, null, null),
                    '/test/baz.html' => array(array('_route' => 'baz2'), null, null, null),
                    '/test/baz3/' => array(array('_route' => 'baz3'), null, null, null),
                    '/foofoo' => array(array('_route' => 'foofoo', 'def' => 'test'), null, null, null),
                    '/spa ce' => array(array('_route' => 'space'), null, null, null),
                    '/multi/new' => array(array('_route' => 'overridden2'), null, null, null),
                    '/multi/hey/' => array(array('_route' => 'hey'), null, null, null),
                    '/ababa' => array(array('_route' => 'ababa'), null, null, null),
                    '/route1' => array(array('_route' => 'route1'), 'a.example.com', null, null),
                    '/c2/route2' => array(array('_route' => 'route2'), 'a.example.com', null, null),
                    '/route4' => array(array('_route' => 'route4'), 'a.example.com', null, null),
                    '/c2/route3' => array(array('_route' => 'route3'), 'b.example.com', null, null),
                    '/route5' => array(array('_route' => 'route5'), 'c.example.com', null, null),
                    '/route6' => array(array('_route' => 'route6'), null, null, null),
                    '/route11' => array(array('_route' => 'route11'), '#^(?P<var1>[^\\.]++)\\.example\\.com$#sDi', null, null),
                    '/route12' => array(array('_route' => 'route12', 'var1' => 'val'), '#^(?P<var1>[^\\.]++)\\.example\\.com$#sDi', null, null),
                    '/route17' => array(array('_route' => 'route17'), null, null, null),
                );

                if (!isset($routes[$pathinfo])) {
                    break;
                }
                list($ret, $requiredHost, $requiredMethods, $requiredSchemes) = $routes[$pathinfo];

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

        $matchedPathinfo = $host.$pathinfo;
        $regexList = array(
            0 => '{^(?'
                .'|[^/]*+(?'
                    .'|/foo/(baz|symfony)(*:34)'
                    .'|/bar(?'
                        .'|/([^/]++)(*:57)'
                        .'|head/([^/]++)(*:77)'
                    .')'
                    .'|/test/([^/]++)/(?'
                        .'|(*:103)'
                    .')'
                    .'|/([\']+)(*:119)'
                    .'|/a/(?'
                        .'|b\'b/([^/]++)(?'
                            .'|(*:148)'
                            .'|(*:156)'
                        .')'
                        .'|(.*)(*:169)'
                        .'|b\'b/([^/]++)(?'
                            .'|(*:192)'
                            .'|(*:200)'
                        .')'
                    .')'
                    .'|/multi/hello(?:/([^/]++))?(*:236)'
                    .'|/([^/]++)/b/([^/]++)(?'
                        .'|(*:267)'
                        .'|(*:275)'
                    .')'
                    .'|/aba/([^/]++)(*:297)'
                .')|(?i:([^\\.]++)\\.example\\.com)(?'
                    .'|/route1(?'
                        .'|3/([^/]++)(*:357)'
                        .'|4/([^/]++)(*:375)'
                    .')'
                .')|(?i:c\\.example\\.com)(?'
                    .'|/route15/([^/]++)(*:425)'
                .')|[^/]*+(?'
                    .'|/route16/([^/]++)(*:460)'
                    .'|/a/(?'
                        .'|a\\.\\.\\.(*:481)'
                        .'|b/(?'
                            .'|([^/]++)(*:502)'
                            .'|c/([^/]++)(*:520)'
                        .')'
                    .')'
                .')'
                .')$}sD',
        );

        foreach ($regexList as $offset => $regex) {
            while (preg_match($regex, $matchedPathinfo, $matches)) {
                switch ($m = (int) $matches['MARK']) {
                    case 103:
                        $matches = array('foo' => $matches[1] ?? null);

                        // baz4
                        return $this->mergeDefaults(array('_route' => 'baz4') + $matches, array());

                        // baz5
                        $ret = $this->mergeDefaults(array('_route' => 'baz5') + $matches, array());
                        if (!isset(($a = array('POST' => 0))[$requestMethod])) {
                            $allow += $a;
                            goto not_baz5;
                        }

                        return $ret;
                        not_baz5:

                        // baz.baz6
                        $ret = $this->mergeDefaults(array('_route' => 'baz.baz6') + $matches, array());
                        if (!isset(($a = array('PUT' => 0))[$requestMethod])) {
                            $allow += $a;
                            goto not_bazbaz6;
                        }

                        return $ret;
                        not_bazbaz6:

                        break;
                    case 148:
                        $matches = array('foo' => $matches[1] ?? null);

                        // foo1
                        $ret = $this->mergeDefaults(array('_route' => 'foo1') + $matches, array());
                        if (!isset(($a = array('PUT' => 0))[$requestMethod])) {
                            $allow += $a;
                            goto not_foo1;
                        }

                        return $ret;
                        not_foo1:

                        break;
                    case 192:
                        $matches = array('foo1' => $matches[1] ?? null);

                        // foo2
                        return $this->mergeDefaults(array('_route' => 'foo2') + $matches, array());

                        break;
                    case 267:
                        $matches = array('_locale' => $matches[1] ?? null, 'foo' => $matches[2] ?? null);

                        // foo3
                        return $this->mergeDefaults(array('_route' => 'foo3') + $matches, array());

                        break;
                    default:
                        $routes = array(
                            34 => array(array('_route' => 'foo', 'def' => 'test'), array('bar'), null, null),
                            57 => array(array('_route' => 'bar'), array('foo'), array('GET' => 0, 'HEAD' => 1), null),
                            77 => array(array('_route' => 'barhead'), array('foo'), array('GET' => 0), null),
                            119 => array(array('_route' => 'quoter'), array('quoter'), null, null),
                            156 => array(array('_route' => 'bar1'), array('bar'), null, null),
                            169 => array(array('_route' => 'overridden'), array('var'), null, null),
                            200 => array(array('_route' => 'bar2'), array('bar1'), null, null),
                            236 => array(array('_route' => 'helloWorld', 'who' => 'World!'), array('who'), null, null),
                            275 => array(array('_route' => 'bar3'), array('_locale', 'bar'), null, null),
                            297 => array(array('_route' => 'foo4'), array('foo'), null, null),
                            357 => array(array('_route' => 'route13'), array('var1', 'name'), null, null),
                            375 => array(array('_route' => 'route14', 'var1' => 'val'), array('var1', 'name'), null, null),
                            425 => array(array('_route' => 'route15'), array('name'), null, null),
                            460 => array(array('_route' => 'route16', 'var1' => 'val'), array('name'), null, null),
                            481 => array(array('_route' => 'a'), array(), null, null),
                            502 => array(array('_route' => 'b'), array('var'), null, null),
                            520 => array(array('_route' => 'c'), array('var'), null, null),
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

                if (520 === $m) {
                    break;
                }
                $regex = substr_replace($regex, 'F', $m - $offset, 1 + strlen($m));
                $offset += strlen($m);
            }
        }
        if ('/' === $pathinfo && !$allow) {
            throw new Symfony\Component\Routing\Exception\NoConfigurationException();
        }

        throw $allow ? new MethodNotAllowedException(array_keys($allow)) : new ResourceNotFoundException();
    }
}
