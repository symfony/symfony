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

        switch ($trimmedPathinfo) {
            default:
                $routes = [
                    '/trailing/simple/no-methods' => [['_route' => 'simple_trailing_slash_no_methods'], null, null, null, true],
                    '/trailing/simple/get-method' => [['_route' => 'simple_trailing_slash_GET_method'], null, ['GET' => 0], null, true],
                    '/trailing/simple/head-method' => [['_route' => 'simple_trailing_slash_HEAD_method'], null, ['HEAD' => 0], null, true],
                    '/trailing/simple/post-method' => [['_route' => 'simple_trailing_slash_POST_method'], null, ['POST' => 0], null, true],
                    '/not-trailing/simple/no-methods' => [['_route' => 'simple_not_trailing_slash_no_methods'], null, null, null, false],
                    '/not-trailing/simple/get-method' => [['_route' => 'simple_not_trailing_slash_GET_method'], null, ['GET' => 0], null, false],
                    '/not-trailing/simple/head-method' => [['_route' => 'simple_not_trailing_slash_HEAD_method'], null, ['HEAD' => 0], null, false],
                    '/not-trailing/simple/post-method' => [['_route' => 'simple_not_trailing_slash_POST_method'], null, ['POST' => 0], null, false],
                ];

                if (!isset($routes[$trimmedPathinfo])) {
                    break;
                }
                list($ret, $requiredHost, $requiredMethods, $requiredSchemes, $hasTrailingSlash) = $routes[$trimmedPathinfo];
                if ('/' !== $pathinfo && $hasTrailingSlash === ($trimmedPathinfo === $pathinfo)) {
                    if ('GET' === $canonicalMethod && (!$requiredMethods || isset($requiredMethods['GET']))) {
                        return $allow = $allowSchemes = [];
                    }
                    break;
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

        $matchedPathinfo = $pathinfo;
        $regexList = [
            0 => '{^(?'
                    .'|/trailing/regex/(?'
                        .'|no\\-methods/([^/]++)(*:46)'
                        .'|get\\-method/([^/]++)(*:73)'
                        .'|head\\-method/([^/]++)(*:101)'
                        .'|post\\-method/([^/]++)(*:130)'
                    .')'
                    .'|/not\\-trailing/regex/(?'
                        .'|no\\-methods/([^/]++)(*:183)'
                        .'|get\\-method/([^/]++)(*:211)'
                        .'|head\\-method/([^/]++)(*:240)'
                        .'|post\\-method/([^/]++)(*:269)'
                    .')'
                .')/?$}sD',
        ];

        foreach ($regexList as $offset => $regex) {
            while (preg_match($regex, $matchedPathinfo, $matches)) {
                switch ($m = (int) $matches['MARK']) {
                    default:
                        $routes = [
                            46 => [['_route' => 'regex_trailing_slash_no_methods'], ['param'], null, null, true, true],
                            73 => [['_route' => 'regex_trailing_slash_GET_method'], ['param'], ['GET' => 0], null, true, true],
                            101 => [['_route' => 'regex_trailing_slash_HEAD_method'], ['param'], ['HEAD' => 0], null, true, true],
                            130 => [['_route' => 'regex_trailing_slash_POST_method'], ['param'], ['POST' => 0], null, true, true],
                            183 => [['_route' => 'regex_not_trailing_slash_no_methods'], ['param'], null, null, false, true],
                            211 => [['_route' => 'regex_not_trailing_slash_GET_method'], ['param'], ['GET' => 0], null, false, true],
                            240 => [['_route' => 'regex_not_trailing_slash_HEAD_method'], ['param'], ['HEAD' => 0], null, false, true],
                            269 => [['_route' => 'regex_not_trailing_slash_POST_method'], ['param'], ['POST' => 0], null, false, true],
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

                if (269 === $m) {
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
