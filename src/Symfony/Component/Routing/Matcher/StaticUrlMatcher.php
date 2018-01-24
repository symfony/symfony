<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Matcher;

use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\NoConfigurationException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RequestContext;

/**
 * StaticUrlMatcher matches URL based on a set of rules dumped by StaticUrlMatcherDumper.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class StaticUrlMatcher extends UrlMatcher
{
    private $dumpedRoutes = array();

    public function __construct(array $dumpedRoutes, RequestContext $context)
    {
        $this->dumpedRoutes = $dumpedRoutes;
        $this->context = $context;
    }

    /**
     * {@inheritdoc}
     */
    public function match($rawPathinfo)
    {
        $allow = array();
        $pathinfo = rawurldecode($rawPathinfo);
        $trimmedPathinfo = rtrim($pathinfo, '/');
        $context = $this->context;
        $request = $this->request ?: $this->createRequest($pathinfo);
        $requestMethod = $context->getMethod();
        $host = $context->getHost();
        $canRedirect = $this instanceof RedirectableUrlMatcherInterface;

        foreach ($this->dumpedRoutes[0] as list($hostRegex, $routes)) {
            if (null === $hostRegex) {
                $hostMatches = array();
            } elseif (!preg_match($hostRegex, $host, $hostMatches)) {
                continue;
            }

            $i = 1;
            $count = \count($routes);
            $nextRoutes = array();

            while (true) {
                for (; $i < $count; ++$i) {
                    if (!\is_array($conditions = $routes[$i][0])) {
                        // nested collection

                        if (null !== $conditions && 0 !== strpos($pathinfo, $conditions)) {
                            continue;
                        }

                        if ($i < $count - 1) {
                            $nextRoutes[] = array(1 + $i, $routes);
                        }

                        $routes = $routes[$i];
                        $count = \count($routes);
                        $i = 0;
                    } else {
                        // single route

                        list(, $expr, $methods, $schemes, $defaults) = $routes[$i];
                        $matches = array();

                        switch ($conditions[0]) {
                            case 'trim':
                                if ($canRedirect) {
                                    if ($conditions[1] !== $trimmedPathinfo) {
                                        continue 2;
                                    }
                                    $redirectTrailingSlash = '/' !== $pathinfo[-1];
                                    break;
                                }
                                $conditions[1] .= '/';
                                // no break
                            case 'path':
                                if ($conditions[1] !== $pathinfo) {
                                    continue 2;
                                }
                                $redirectTrailingSlash = false;
                                break;
                            case 'start':
                                if (0 !== strpos($pathinfo, $conditions[2])) {
                                    continue 2;
                                }
                                // no break
                            case 'match':
                                if (!preg_match($conditions[1], $pathinfo, $matches)) {
                                    continue 2;
                                }
                                if ($redirectTrailingSlash = isset($matches[''])) {
                                    $redirectTrailingSlash = $canRedirect && '/' !== $matches[''];
                                    unset($matches['']);
                                }
                                break;
                        }

                        if (null !== $expr && !$this->dumpedRoutes[1][$expr]($context, $request)) {
                            continue;
                        }

                        if ($methods && !isset($methods[$requestMethod]) && ('HEAD' !== $requestMethod || !isset($methods['GET']))) {
                            $allow += $methods;
                            continue;
                        }

                        if ($matches || $hostMatches) {
                            $ret = $this->mergeDefaults(array_replace($hostMatches, $matches), $defaults);
                            $ret['_route'] = $defaults['_route'];
                        } else {
                            $ret = $defaults;
                        }

                        if ($redirectTrailingSlash) {
                            return array_replace($ret, $this->redirect($rawPathinfo.'/', $defaults['_route']));
                        }

                        if ($schemes && !isset($schemes[$context->getScheme()])) {
                            if ($canRedirect) {
                                return array_replace($ret, $this->redirect($rawPathinfo, $defaults['_route'], key($schemes)));
                            }
                            continue;
                        }

                        return $ret;
                    }
                }

                if (!$nextRoutes) {
                    break;
                }

                list($i, $routes) = array_pop($nextRoutes);
                $count = \count($routes);
            }
        }

        if (!$this->dumpedRoutes[0] && '/' === $pathinfo) {
            throw new NoConfigurationException();
        }

        throw 0 < count($allow) ? new MethodNotAllowedException(array_values($allow)) : new ResourceNotFoundException();
    }
}
