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
        $allow = $allowSchemes = [];
        if ($ret = $this->doMatch($pathinfo, $allow, $allowSchemes)) {
            return $ret;
        }
        if ($allow) {
            throw new MethodNotAllowedException(array_keys($allow));
        }
        if (!in_array($this->context->getMethod(), ['HEAD', 'GET'], true)) {
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
        } elseif ('/' !== $trimmedPathinfo = rtrim($pathinfo, '/') ?: '/') {
            $pathinfo = $trimmedPathinfo === $pathinfo ? $pathinfo.'/' : $trimmedPathinfo;
            if ($ret = $this->doMatch($pathinfo, $allow, $allowSchemes)) {
                return $this->redirect($pathinfo, $ret['_route']) + $ret;
            }
            if ($allowSchemes) {
                goto redirect_scheme;
            }
        }

        throw new ResourceNotFoundException();
    }

    private function doMatch(string $pathinfo, array &$allow = [], array &$allowSchemes = []): array
    {
        $allow = $allowSchemes = [];
        $pathinfo = rawurldecode($pathinfo) ?: '/';
        $trimmedPathinfo = rtrim($pathinfo, '/') ?: '/';
        $context = $this->context;
        $requestMethod = $canonicalMethod = $context->getMethod();

        if ('HEAD' === $requestMethod) {
            $canonicalMethod = 'GET';
        }

        $matchedPathinfo = $pathinfo;
        $regexList = [
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
                .')/?$}sD',
        ];

        foreach ($regexList as $offset => $regex) {
            while (preg_match($regex, $matchedPathinfo, $matches)) {
                switch ($m = (int) $matches['MARK']) {
                    default:
                        $routes = [
                            32 => [['_route' => 'a', '_locale' => 'en'], ['_locale'], null, null, true, false],
                            46 => [['_route' => 'b', '_locale' => 'en'], ['_locale'], null, null, false, false],
                            58 => [['_route' => 'c', '_locale' => 'en'], ['_locale', 'id'], null, null, false, true],
                            75 => [['_route' => 'd', '_locale' => 'en'], ['_locale', 'id'], null, null, false, false],
                            94 => [['_route' => 'e', '_locale' => 'en'], ['_locale', 'id'], null, null, false, false],
                            110 => [['_route' => 'f', '_locale' => 'en'], ['_locale'], null, null, true, false],
                            130 => [['_route' => 'g', '_locale' => 'en'], ['_locale'], null, null, false, false],
                            154 => [['_route' => 'h', '_locale' => 'en'], ['_locale', 'page'], null, null, false, true],
                            175 => [['_route' => 'i', '_locale' => 'en'], ['_locale', 'page'], null, null, false, true],
                            202 => [['_route' => 'j', '_locale' => 'en'], ['_locale', 'id'], null, null, false, false],
                            216 => [['_route' => 'k', '_locale' => 'en'], ['_locale'], null, null, false, false],
                            234 => [['_route' => 'l', '_locale' => 'en'], ['_locale'], null, null, false, false],
                            245 => [['_route' => 'm', '_locale' => 'en'], ['_locale'], null, null, false, false],
                            264 => [['_route' => 'n', '_locale' => 'en'], ['_locale'], null, null, false, true],
                        ];

                        list($ret, $vars, $requiredMethods, $requiredSchemes, $hasTrailingSlash, $hasTrailingVar) = $routes[$m];

                        $hasTrailingVar = $trimmedPathinfo !== $pathinfo && $hasTrailingVar;
                        if ('/' !== $pathinfo && !$hasTrailingVar && $hasTrailingSlash === ($trimmedPathinfo === $pathinfo)) {
                            if ('GET' === $canonicalMethod && (!$requiredMethods || isset($requiredMethods['GET']))) {
                                return $allow = $allowSchemes = [];
                            }
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

        return [];
    }
}
