<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset\Preload;

/**
 * Manages resources to preload according to the W3C "Preload" specification.
 *
 * @see https://www.w3.org/TR/preload/
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
interface PreloadManagerInterface
{
    /**
     * Adds an element to the list of resources to preload.
     *
     * @param string $uri    The resource URI
     * @param string $as     A valid destination according to https://fetch.spec.whatwg.org/#concept-request-destination
     * @param bool   $nopush If this asset should not be pushed over HTTP/2
     */
    public function addResource($uri, $as = '', $nopush = false);

    /**
     * Clears the list of resources.
     */
    public function clear();

    /**
     * Builds the value of the preload Link HTTP header.
     *
     * @return string|null
     */
    public function buildLinkValue();
}
