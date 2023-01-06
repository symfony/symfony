<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset\Context;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Uses a RequestStack to populate the context.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RequestStackContext implements ContextInterface
{
    private RequestStack $requestStack;
    private string $basePath;
    private bool $secure;

    public function __construct(RequestStack $requestStack, string $basePath = '', bool $secure = false)
    {
        $this->requestStack = $requestStack;
        $this->basePath = $basePath;
        $this->secure = $secure;
    }

    public function getBasePath(): string
    {
        if (!$request = $this->requestStack->getMainRequest()) {
            return $this->basePath;
        }

        return $request->getBasePath();
    }

    public function isSecure(): bool
    {
        if (!$request = $this->requestStack->getMainRequest()) {
            return $this->secure;
        }

        return $request->isSecure();
    }
}
