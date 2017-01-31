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

/**
 * Asset package with preloading support interface.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
interface PreloadedPackageInterface extends PackageInterface
{
    /**
     * Returns an absolute or root-relative public path and adds it to the URLs to preload.
     *
     * @param string $path   A path
     * @param string $as     A valid destination according to https://fetch.spec.whatwg.org/#concept-request-destination
     * @param bool   $nopush If this asset should not be pushed over HTTP/2
     * @return string
     */
    public function getAndPreloadUrl($path, $as = '', $nopush = false);
}
