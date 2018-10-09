<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Matcher\Dumper;

use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\NoConfigurationException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\RedirectableUrlMatcherInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
trait PhpMatcherTrait
{
    private $matchHost = false;
    private $staticRoutes = array();
    private $regexpList = array();
    private $dynamicRoutes = array();
    private $checkCondition;

    public function match($pathinfo)
    {
        $allow = $allowSchemes = array();
        if ($ret = $this->doMatch($pathinfo, $allow, $allowSchemes)) {
            return $ret;
        }
        if ($allow) {
            throw new MethodNotAllowedException(array_keys($allow));
        }
        if (!$this instanceof RedirectableUrlMatcherInterface) {
            throw new ResourceNotFoundException();
        }
        if (!\in_array($this->context->getMethod(), array('HEAD', 'GET'), true)) {
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

        if ($this->matchHost) {
            $host = strtolower($context->getHost());
        }

        if ('HEAD' === $requestMethod) {
            $canonicalMethod = 'GET';
        }

        foreach ($this->staticRoutes[$pathinfo] ?? array() as list($ret, $requiredHost, $requiredMethods, $requiredSchemes, $condition)) {
            if ($condition && !($this->checkCondition)($condition, $context, 0 < $condition ? $request ?? $request = $this->request ?: $this->createRequest($pathinfo) : null)) {
                continue;
            }

            if ($requiredHost) {
                if ('#' !== $requiredHost[0] ? $requiredHost !== $host : !preg_match($requiredHost, $host, $hostMatches)) {
                    continue;
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
                continue;
            }
            if (!$hasRequiredScheme) {
                $allowSchemes += $requiredSchemes;
                continue;
            }

            return $ret;
        }

        $matchedPathinfo = $this->matchHost ? $host.'.'.$pathinfo : $pathinfo;

        foreach ($this->regexpList as $offset => $regex) {
            while (preg_match($regex, $matchedPathinfo, $matches)) {
                foreach ($this->dynamicRoutes[$m = (int) $matches['MARK']] as list($ret, $vars, $requiredMethods, $requiredSchemes, $condition)) {
                    if ($condition && !($this->checkCondition)($condition, $context, 0 < $condition ? $request ?? $request = $this->request ?: $this->createRequest($pathinfo) : null)) {
                        continue;
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
                        continue;
                    }
                    if (!$hasRequiredScheme) {
                        $allowSchemes += $requiredSchemes;
                        continue;
                    }

                    return $ret;
                }

                $regex = substr_replace($regex, 'F', $m - $offset, 1 + \strlen($m));
                $offset += \strlen($m);
            }
        }

        if ('/' === $pathinfo && !$allow && !$allowSchemes) {
            throw new NoConfigurationException();
        }

        return null;
    }
}
