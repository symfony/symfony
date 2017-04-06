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

use Fig\Link\GenericLinkProvider;
use Psr\Link\EvolvableLinkProviderInterface;
use Psr\Link\LinkInterface;

/**
 * Stores an instance of an EvolvableLinkProviderInterface.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final class WebLinkManager implements WebLinkManagerInterface
{
    private $linkProvider;

    public function __construct(EvolvableLinkProviderInterface $linkProvider = null)
    {
        $this->linkProvider = $linkProvider ? $linkProvider : new GenericLinkProvider();
    }

    /**
     * {@inheritdoc}
     */
    public function add(LinkInterface $link)
    {
        $this->linkProvider = $this->linkProvider->withLink($link);
    }

    /**
     * {@inheritdoc}
     */
    public function getLinkProvider()
    {
        return $this->linkProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        foreach ($this->linkProvider->getLinks() as $link) {
            $this->linkProvider = $this->linkProvider->withoutLink($link);
        }
    }
}
