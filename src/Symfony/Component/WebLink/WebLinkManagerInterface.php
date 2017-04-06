<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\WebLink;

use Psr\Link\LinkInterface;
use Psr\Link\LinkProviderInterface;

/**
 * Manages connections between resources according to the W3C specifications.
 *
 * @see https://www.w3.org/TR/html5/links.html
 * @see https://www.w3.org/TR/resource-hints/
 * @see https://www.w3.org/TR/preload/
 * @see http://microformats.org/wiki/existing-rel-values#HTML5_link_type_extensions
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
interface WebLinkManagerInterface
{
    // Relations defined in https://www.w3.org/TR/html5/links.html#links and applicable on link elements
    const REL_ALTERNATE = 'alternate';
    const REL_AUTHOR = 'author';
    const REL_HELP = 'help';
    const REL_ICON = 'icon';
    const REL_LICENSE = 'license';
    const REL_SEARCH = 'search';
    const REL_STYLESHEET = 'stylesheet';
    const REL_NEXT = 'next';
    const REL_PREV = 'prev';

    // Relation defined in https://www.w3.org/TR/preload/
    const REL_PRELOAD = 'preload';

    // Relations defined in https://www.w3.org/TR/resource-hints/
    const REL_DNS_PREFETCH = 'dns-prefetch';
    const REL_PRECONNECT = 'preconnect';
    const REL_PREFETCH = 'prefetch';
    const REL_PRERENDER = 'prerender';

    /**
     * Adds a Link to the list.
     *
     * @param $link LinkInterface
     */
    public function add(LinkInterface $link);

    /**
     * Gets a LinkProviderInterface instance.
     *
     * @return LinkProviderInterface
     */
    public function getLinkProvider();

    /**
     * Clears the list of links.
     */
    public function clear();
}
