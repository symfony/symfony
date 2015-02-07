<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler;

/**
 * Any HTML element that can link to an URI.
 *
 * @api
 */
interface UriElementInterface
{
    /**
     * Gets the node associated with this element.
     *
     * @return \DOMElement A \DOMElement instance
     */
    public function getNode();

    /**
     * Gets the method associated with this element.
     *
     * @return string The method
     *
     * @api
     */
    public function getMethod();

    /**
     * Gets the URI associated with this element.
     *
     * @return string The URI
     *
     * @api
     */
    public function getUri();
}
