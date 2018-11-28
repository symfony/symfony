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

use Symfony\Component\Routing\Exception\ExceptionInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class RedirectableUrlMatcher extends UrlMatcher implements RedirectableUrlMatcherInterface
{
    /**
     * {@inheritdoc}
     */
    public function match($pathinfo)
    {
        try {
            return parent::match($pathinfo);
        } catch (ResourceNotFoundException $e) {
            if (!\in_array($this->context->getMethod(), array('HEAD', 'GET'), true)) {
                throw $e;
            }

            if ($this->allowSchemes) {
                return $this->redirectScheme($pathinfo, $e);
            } elseif ('/' === $pathinfo) {
                throw $e;
            } else {
                try {
                    $pathinfo = '/' !== $pathinfo[-1] ? $pathinfo.'/' : substr($pathinfo, 0, -1);
                    $ret = parent::match($pathinfo);

                    return $this->redirect($pathinfo, $ret['_route'] ?? null) + $ret;
                } catch (ExceptionInterface $e2) {
                    if ($this->allowSchemes) {
                        return $this->redirectScheme($pathinfo, $e);
                    }
                    throw $e;
                }
            }
        }
    }

    private function redirectScheme(string $pathinfo, ResourceNotFoundException $originalException)
    {
        $scheme = $this->context->getScheme();
        $this->context->setScheme(current($this->allowSchemes));
        try {
            $ret = parent::match($pathinfo);

            return $this->redirect($pathinfo, $ret['_route'] ?? null, $this->context->getScheme()) + $ret;
        } catch (ExceptionInterface $e) {
            throw $originalException;
        } finally {
            $this->context->setScheme($scheme);
        }
    }
}
