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
        $pathinfo = rawurldecode($rawPathinfo) ?: '/';
        $context = $this->context;
        $requestMethod = $canonicalMethod = $context->getMethod();

        if ('HEAD' === $requestMethod) {
            $canonicalMethod = 'GET';
        }

        $matchedPathinfo = $pathinfo;
        $regexList = array(
            0 => '{^(?'
                    .'|/(en|fr)/(?'
                        .'|admin/post(?'
                            .'|(*:32)'
                            .'|/(?'
                                .'|new(*:46)'
                                .'|(\\d+)(*:58)'
                                .'|(\\d+)/edit(*:75)'
                                .'|(\\d+)/delete(*:94)'
                            .')'
                        .')'
                        .'|blog(?'
                            .'|(*:110)'
                            .'|/(?'
                                .'|rss\\.xml(*:130)'
                                .'|p(?'
                                    .'|age/([^/]++)(*:154)'
                                    .'|osts/([^/]++)(*:175)'
                                .')'
                                .'|comments/(\\d+)/new(*:202)'
                                .'|search(*:216)'
                            .')'
                        .')'
                        .'|log(?'
                            .'|in(*:234)'
                            .'|out(*:245)'
                        .')'
                    .')'
                    .'|/(en|fr)?(*:264)'
                .')(?:/?)$}sD',
        );

        foreach ($regexList as $offset => $regex) {
            while (preg_match($regex, $matchedPathinfo, $matches)) {
                switch ($m = (int) $matches['MARK']) {
                    default:
                        $routes = array(
                            32 => array(array('_route' => 'a', '_locale' => 'en'), array('_locale'), null, null, true),
                            46 => array(array('_route' => 'b', '_locale' => 'en'), array('_locale'), null, null, false),
                            58 => array(array('_route' => 'c', '_locale' => 'en'), array('_locale', 'id'), null, null, false),
                            75 => array(array('_route' => 'd', '_locale' => 'en'), array('_locale', 'id'), null, null, false),
                            94 => array(array('_route' => 'e', '_locale' => 'en'), array('_locale', 'id'), null, null, false),
                            110 => array(array('_route' => 'f', '_locale' => 'en'), array('_locale'), null, null, true),
                            130 => array(array('_route' => 'g', '_locale' => 'en'), array('_locale'), null, null, false),
                            154 => array(array('_route' => 'h', '_locale' => 'en'), array('_locale', 'page'), null, null, false),
                            175 => array(array('_route' => 'i', '_locale' => 'en'), array('_locale', 'page'), null, null, false),
                            202 => array(array('_route' => 'j', '_locale' => 'en'), array('_locale', 'id'), null, null, false),
                            216 => array(array('_route' => 'k', '_locale' => 'en'), array('_locale'), null, null, false),
                            234 => array(array('_route' => 'l', '_locale' => 'en'), array('_locale'), null, null, false),
                            245 => array(array('_route' => 'm', '_locale' => 'en'), array('_locale'), null, null, false),
                            264 => array(array('_route' => 'n', '_locale' => 'en'), array('_locale'), null, null, false),
                        );

                        list($ret, $vars, $requiredMethods, $requiredSchemes, $hasTrailingSlash) = $routes[$m];

                        if ('/' !== $pathinfo && $hasTrailingSlash !== ('/' === $pathinfo[-1])) {
                            return null;
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

                if (264 === $m) {
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
