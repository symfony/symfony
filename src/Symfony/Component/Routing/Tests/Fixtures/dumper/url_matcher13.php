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
        $pathinfo = rawurldecode($rawPathinfo) ?: '/';
        $context = $this->context;
        $requestMethod = $canonicalMethod = $context->getMethod();
        $host = strtolower($context->getHost());

        if ('HEAD' === $requestMethod) {
            $canonicalMethod = 'GET';
        }

        $matchedPathinfo = $host.'.'.$pathinfo;
        $regexList = array(
            0 => '{^(?'
                .'|(?i:([^\\.]++)\\.exampple\\.com)\\.(?'
                    .'|/abc([^/]++)(?'
                        .'|(*:56)'
                    .')'
                .')'
                .')(?:/?)$}sD',
        );

        foreach ($regexList as $offset => $regex) {
            while (preg_match($regex, $matchedPathinfo, $matches)) {
                switch ($m = (int) $matches['MARK']) {
                    case 56:
                        $matches = array('foo' => $matches[1] ?? null, 'foo' => $matches[2] ?? null);

                        // r1
                        if ('/' !== $pathinfo && '/' === $pathinfo[-1] && preg_match($regex, substr($pathinfo, 0, -1), $n) && $m === (int) $n['MARK']) {
                            goto not_r1;
                        }

                        return $this->mergeDefaults(array('_route' => 'r1') + $matches, array());
                        not_r1:

                        // r2
                        if ('/' !== $pathinfo && '/' === $pathinfo[-1] && preg_match($regex, substr($pathinfo, 0, -1), $n) && $m === (int) $n['MARK']) {
                            goto not_r2;
                        }

                        return $this->mergeDefaults(array('_route' => 'r2') + $matches, array());
                        not_r2:

                        break;
                }

                if (56 === $m) {
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
