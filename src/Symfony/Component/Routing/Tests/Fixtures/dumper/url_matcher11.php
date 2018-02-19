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

        $matchedPathinfo = $pathinfo;
        $regexList = array(
            0 => '{^(?'
                    .'|/(en|fr)(?'
                        .'|/admin/post(?'
                            .'|/?(*:34)'
                            .'|/new(*:45)'
                            .'|/(\\d+)(?'
                                .'|(*:61)'
                                .'|/edit(*:73)'
                                .'|/delete(*:87)'
                            .')'
                        .')'
                        .'|/blog(?'
                            .'|/?(*:106)'
                            .'|/rss\\.xml(*:123)'
                            .'|/p(?'
                                .'|age/([^/]++)(*:148)'
                                .'|osts/([^/]++)(*:169)'
                            .')'
                            .'|/comments/(\\d+)/new(*:197)'
                            .'|/search(*:212)'
                        .')'
                        .'|/log(?'
                            .'|in(*:230)'
                            .'|out(*:241)'
                        .')'
                    .')'
                    .'|/(en|fr)?(*:260)'
                .')$}sD',
        );

        foreach ($regexList as $offset => $regex) {
            while (preg_match($regex, $matchedPathinfo, $matches)) {
                switch ($m = (int) $matches['MARK']) {
                    default:
                        $routes = array(
                            34 => array(array('_route' => 'a', '_locale' => 'en'), array('_locale'), null, null, true),
                            45 => array(array('_route' => 'b', '_locale' => 'en'), array('_locale'), null, null),
                            61 => array(array('_route' => 'c', '_locale' => 'en'), array('_locale', 'id'), null, null),
                            73 => array(array('_route' => 'd', '_locale' => 'en'), array('_locale', 'id'), null, null),
                            87 => array(array('_route' => 'e', '_locale' => 'en'), array('_locale', 'id'), null, null),
                            106 => array(array('_route' => 'f', '_locale' => 'en'), array('_locale'), null, null, true),
                            123 => array(array('_route' => 'g', '_locale' => 'en'), array('_locale'), null, null),
                            148 => array(array('_route' => 'h', '_locale' => 'en'), array('_locale', 'page'), null, null),
                            169 => array(array('_route' => 'i', '_locale' => 'en'), array('_locale', 'page'), null, null),
                            197 => array(array('_route' => 'j', '_locale' => 'en'), array('_locale', 'id'), null, null),
                            212 => array(array('_route' => 'k', '_locale' => 'en'), array('_locale'), null, null),
                            230 => array(array('_route' => 'l', '_locale' => 'en'), array('_locale'), null, null),
                            241 => array(array('_route' => 'm', '_locale' => 'en'), array('_locale'), null, null),
                            260 => array(array('_route' => 'n', '_locale' => 'en'), array('_locale'), null, null),
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

                if (260 === $m) {
                    break;
                }
                $regex = substr_replace($regex, 'F', $m - $offset, 1 + strlen($m));
                $offset += strlen($m);
            }
        }

        throw $allow ? new MethodNotAllowedException(array_keys($allow)) : new ResourceNotFoundException();
    }
}
