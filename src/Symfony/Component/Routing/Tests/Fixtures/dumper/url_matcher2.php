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
        $host = strtolower($context->getHost());

        if ('HEAD' === $requestMethod) {
            $canonicalMethod = 'GET';
        }

        switch ($trimmedPathinfo) {
            default:
                $routes = array(
                    '/test/baz' => array(array('_route' => 'baz'), null, null, null),
                    '/test/baz.html' => array(array('_route' => 'baz2'), null, null, null),
                    '/test/baz3' => array(array('_route' => 'baz3'), null, null, null, true),
                    '/foofoo' => array(array('_route' => 'foofoo', 'def' => 'test'), null, null, null),
                    '/spa ce' => array(array('_route' => 'space'), null, null, null),
                    '/multi/new' => array(array('_route' => 'overridden2'), null, null, null),
                    '/multi/hey' => array(array('_route' => 'hey'), null, null, null, true),
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
                    '/secure' => array(array('_route' => 'secure'), null, null, array('https' => 0)),
                    '/nonsecure' => array(array('_route' => 'nonsecure'), null, null, array('http' => 0)),
                );

                if (!isset($routes[$trimmedPathinfo])) {
                    break;
                }
                list($ret, $requiredHost, $requiredMethods, $requiredSchemes) = $routes[$trimmedPathinfo];

                if ($requiredHost) {
                    if ('#' !== $requiredHost[0] ? $requiredHost !== $host : !preg_match($requiredHost, $host, $hostMatches)) {
                        break;
                    }
                    if ('#' === $requiredHost[0] && $hostMatches) {
                        $hostMatches['_route'] = $ret['_route'];
                        $ret = $this->mergeDefaults($hostMatches, $ret);
                    }
                }

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

        $matchedPathinfo = $host.$pathinfo;
        $regexList = array(
            0 => '{^(?'
                .'|[^/]*+(?'
                    .'|/foo/(baz|symfony)(*:34)'
                    .'|/bar(?'
                        .'|/([^/]++)(*:57)'
                        .'|head/([^/]++)(*:77)'
                    .')'
                    .'|/test/([^/]++)(?'
                        .'|/?(*:104)'
                    .')'
                    .'|/([\']+)(*:120)'
                    .'|/a(?'
                        .'|/b\'b/([^/]++)(?'
                            .'|(*:149)'
                            .'|(*:157)'
                        .')'
                        .'|/(.*)(*:171)'
                        .'|/b\'b/([^/]++)(?'
                            .'|(*:195)'
                            .'|(*:203)'
                        .')'
                    .')'
                    .'|/multi/hello(?:/([^/]++))?(*:239)'
                    .'|/([^/]++)/b/([^/]++)(?'
                        .'|(*:270)'
                        .'|(*:278)'
                    .')'
                    .'|/aba/([^/]++)(*:300)'
                .')|(?i:([^\\.]++)\\.example\\.com)(?'
                    .'|/route1(?'
                        .'|3/([^/]++)(*:360)'
                        .'|4/([^/]++)(*:378)'
                    .')'
                .')|(?i:c\\.example\\.com)(?'
                    .'|/route15/([^/]++)(*:428)'
                .')|[^/]*+(?'
                    .'|/route16/([^/]++)(*:463)'
                    .'|/a(?'
                        .'|/a\\.\\.\\.(*:484)'
                        .'|/b(?'
                            .'|/([^/]++)(*:506)'
                            .'|/c/([^/]++)(*:525)'
                        .')'
                    .')'
                .')'
                .')$}sD',
        );

        foreach ($regexList as $offset => $regex) {
            while (preg_match($regex, $matchedPathinfo, $matches)) {
                switch ($m = (int) $matches['MARK']) {
                    case 104:
                        $matches = array('foo' => $matches[1] ?? null);

                        // baz4
                        $ret = $this->mergeDefaults(array_replace($matches, array('_route' => 'baz4')), array());
                        if ('/' === $pathinfo[-1]) {
                            // no-op
                        } elseif ('GET' !== $canonicalMethod) {
                            $allow['GET'] = 'GET';
                            goto not_baz4;
                        } else {
                            return array_replace($ret, $this->redirect($rawPathinfo.'/', 'baz4'));
                        }

                        return $ret;
                        not_baz4:

                        // baz5
                        if ('/' === $pathinfo[-1]) {
                            $ret = $this->mergeDefaults(array_replace($matches, array('_route' => 'baz5')), array());
                            if (!isset(($a = array('POST' => 0))[$requestMethod])) {
                                $allow += $a;
                                goto not_baz5;
                            }

                            return $ret;
                        }
                        not_baz5:

                        // baz.baz6
                        if ('/' === $pathinfo[-1]) {
                            $ret = $this->mergeDefaults(array_replace($matches, array('_route' => 'baz.baz6')), array());
                            if (!isset(($a = array('PUT' => 0))[$requestMethod])) {
                                $allow += $a;
                                goto not_bazbaz6;
                            }

                            return $ret;
                        }
                        not_bazbaz6:

                        break;
                    case 149:
                        $matches = array('foo' => $matches[1] ?? null);

                        // foo1
                        $ret = $this->mergeDefaults(array_replace($matches, array('_route' => 'foo1')), array());
                        if (!isset(($a = array('PUT' => 0))[$requestMethod])) {
                            $allow += $a;
                            goto not_foo1;
                        }

                        return $ret;
                        not_foo1:

                        break;
                    case 195:
                        $matches = array('foo1' => $matches[1] ?? null);

                        // foo2
                        return $this->mergeDefaults(array_replace($matches, array('_route' => 'foo2')), array());

                        break;
                    case 270:
                        $matches = array('_locale' => $matches[1] ?? null, 'foo' => $matches[2] ?? null);

                        // foo3
                        return $this->mergeDefaults(array_replace($matches, array('_route' => 'foo3')), array());

                        break;
                    default:
                        $routes = array(
                            34 => array(array('_route' => 'foo', 'def' => 'test'), array('bar'), null, null),
                            57 => array(array('_route' => 'bar'), array('foo'), array('GET' => 0, 'HEAD' => 1), null),
                            77 => array(array('_route' => 'barhead'), array('foo'), array('GET' => 0), null),
                            120 => array(array('_route' => 'quoter'), array('quoter'), null, null),
                            157 => array(array('_route' => 'bar1'), array('bar'), null, null),
                            171 => array(array('_route' => 'overridden'), array('var'), null, null),
                            203 => array(array('_route' => 'bar2'), array('bar1'), null, null),
                            239 => array(array('_route' => 'helloWorld', 'who' => 'World!'), array('who'), null, null),
                            278 => array(array('_route' => 'bar3'), array('_locale', 'bar'), null, null),
                            300 => array(array('_route' => 'foo4'), array('foo'), null, null),
                            360 => array(array('_route' => 'route13'), array('var1', 'name'), null, null),
                            378 => array(array('_route' => 'route14', 'var1' => 'val'), array('var1', 'name'), null, null),
                            428 => array(array('_route' => 'route15'), array('name'), null, null),
                            463 => array(array('_route' => 'route16', 'var1' => 'val'), array('name'), null, null),
                            484 => array(array('_route' => 'a'), array(), null, null),
                            506 => array(array('_route' => 'b'), array('var'), null, null),
                            525 => array(array('_route' => 'c'), array('var'), null, null),
                        );

                        list($ret, $vars, $requiredMethods, $requiredSchemes) = $routes[$m];

                        foreach ($vars as $i => $v) {
                            if (isset($matches[1 + $i])) {
                                $ret[$v] = $matches[1 + $i];
                            }
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

                if (525 === $m) {
                    break;
                }
                $regex = substr_replace($regex, 'F', $m - $offset, 1 + strlen($m));
                $offset += strlen($m);
            }
        }

        throw $allow ? new MethodNotAllowedException(array_keys($allow)) : new ResourceNotFoundException();
    }
}
