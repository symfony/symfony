<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Link;

/**
 * Manages connections between resources according to the W3C specifications.
 *
 * @see https://www.w3.org/TR/html5/links.html#links
 * @see https://www.w3.org/TR/preload/
 * @see https://www.w3.org/TR/resource-hints/
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
interface LinkManagerInterface
{
    /**
     * Adds an element to the list of resources to preload.
     *
     * @param string $uri        The relation URI
     * @param string $rel        The relation type (e.g. "preload", "prefetch", "prerender" or "dns-prefetch")
     * @param array  $attributes The attributes of this link (e.g. "array('as' => true)", "array('pr' => 0.5)")
     */
    public function add($uri, $rel, array $attributes = array());

    /**
     * Clears the list of resources.
     */
    public function clear();

    /**
     * Builds the values of the "Link" HTTP headers.
     *
     * @return string|null
     */
    public function buildValues();
}
