<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RequestPathPackage extends PathPackage
{
    private $requestStack;

    /**
     * @param RequestStack $request The request stack
     * @param string       $version The version
     * @param string       $format  The version format
     */
    public function __construct(RequestStack $requestStack, $version = null, $format = null)
    {
        $this->requestStack = $requestStack;

        parent::__construct(null, $version, $format);
    }

    /**
     * {@inheritdoc}
     */
    public function getBasePath()
    {
        return $this->requestStack->getCurrentRequest()->getBasePath().'/';
    }
}
