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

        $matchedPathinfo = $pathinfo;
        $regexList = array(
            0 => '{^(?'
                    .'|/(en|fr)/(?'
                        .'|admin/post/(?'
                            .'|(*:33)'
                            .'|new(*:43)'
                            .'|(\\d+)(*:55)'
                            .'|(\\d+)/edit(*:72)'
                            .'|(\\d+)/delete(*:91)'
                        .')'
                        .'|blog/(?'
                            .'|(*:107)'
                            .'|rss\\.xml(*:123)'
                            .'|p(?'
                                .'|age/([^/]++)(*:147)'
                                .'|osts/([^/]++)(*:168)'
                            .')'
                            .'|comments/(\\d+)/new(*:195)'
                            .'|search(*:209)'
                        .')'
                        .'|log(?'
                            .'|in(*:226)'
                            .'|out(*:237)'
                        .')'
                    .')'
                    .'|/(en|fr)?(*:256)'
                .')$}sD',
        );

        foreach ($regexList as $offset => $regex) {
            while (preg_match($regex, $matchedPathinfo, $matches)) {
                switch ($m = (int) $matches['MARK']) {
                    default:
                        $routes = array(
                            33 => array(array('_route' => 'a', '_locale' => 'en'), array('_locale'), null, null),
                            43 => array(array('_route' => 'b', '_locale' => 'en'), array('_locale'), null, null),
                            55 => array(array('_route' => 'c', '_locale' => 'en'), array('_locale', 'id'), null, null),
                            72 => array(array('_route' => 'd', '_locale' => 'en'), array('_locale', 'id'), null, null),
                            91 => array(array('_route' => 'e', '_locale' => 'en'), array('_locale', 'id'), null, null),
                            107 => array(array('_route' => 'f', '_locale' => 'en'), array('_locale'), null, null),
                            123 => array(array('_route' => 'g', '_locale' => 'en'), array('_locale'), null, null),
                            147 => array(array('_route' => 'h', '_locale' => 'en'), array('_locale', 'page'), null, null),
                            168 => array(array('_route' => 'i', '_locale' => 'en'), array('_locale', 'page'), null, null),
                            195 => array(array('_route' => 'j', '_locale' => 'en'), array('_locale', 'id'), null, null),
                            209 => array(array('_route' => 'k', '_locale' => 'en'), array('_locale'), null, null),
                            226 => array(array('_route' => 'l', '_locale' => 'en'), array('_locale'), null, null),
                            237 => array(array('_route' => 'm', '_locale' => 'en'), array('_locale'), null, null),
                            256 => array(array('_route' => 'n', '_locale' => 'en'), array('_locale'), null, null),
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

                if (256 === $m) {
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
