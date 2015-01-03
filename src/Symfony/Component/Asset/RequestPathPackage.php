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
 * Package that adds a base path to asset URLs in addition to a version.
 *
 * In addition to the provided base path, this package also automatically
 * prepends the current request base path to allow a website to be hosted
 * easily under any given path under the Web Server root directory.
 *
 * When no request is available, it falls back to only use the configured
 * base path.
 *
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
    public function __construct(RequestStack $requestStack, $basePath = '', $version = null, $format = null)
    {
        $this->requestStack = $requestStack;

        parent::__construct($basePath, $version, $format);
    }

    /**
     * {@inheritdoc}
     */
    public function getBasePath()
    {
        if (!$request = $this->requestStack->getCurrentRequest()) {
            return parent::getBasePath();
        }

        return $request->getBasePath().parent::getBasePath();
    }
}
