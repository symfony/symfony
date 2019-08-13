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
    private $requestStack;
    private $basePath;
    private $secure;

    /**
     * @param string $basePath
     * @param bool   $secure
     */
    public function __construct(RequestStack $requestStack, $basePath = '', $secure = false)
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
