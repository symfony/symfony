<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Asset\Context;

use Symphony\Component\HttpFoundation\RequestStack;

/**
 * Uses a RequestStack to populate the context.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class RequestStackContext implements ContextInterface
{
    private $requestStack;
    private $basePath;
    private $secure;

    public function __construct(RequestStack $requestStack, string $basePath = '', bool $secure = false)
    {
        $this->requestStack = $requestStack;
        $this->basePath = $basePath;
        $this->secure = $secure;
    }

    /**
     * {@inheritdoc}
     */
    public function getBasePath()
    {
        if (!$request = $this->requestStack->getMasterRequest()) {
            return $this->basePath;
        }

        return $request->getBasePath();
    }

    /**
     * {@inheritdoc}
     */
    public function isSecure()
    {
        if (!$request = $this->requestStack->getMasterRequest()) {
            return $this->secure;
        }

        return $request->isSecure();
    }
}
