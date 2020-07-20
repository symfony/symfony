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

use Psr\Link\EvolvableLinkProviderInterface;
use Psr\Link\LinkInterface;

class GenericLinkProvider implements EvolvableLinkProviderInterface
{
    /**
     * @var LinkInterface[]
     */
    private $links = [];

    /**
     * @param LinkInterface[] $links
     */
    public function __construct(array $links = [])
    {
        $that = $this;

        foreach ($links as $link) {
            $that = $that->withLink($link);
        }

        $this->links = $that->links;
    }

    /**
     * {@inheritdoc}
     */
    public function getLinks(): array
    {
        return array_values($this->links);
    }

    /**
     * {@inheritdoc}
     */
    public function getLinksByRel($rel): array
    {
        $links = [];

        foreach ($this->links as $link) {
            if (\in_array($rel, $link->getRels())) {
                $links[] = $link;
            }
        }

        return $links;
    }

    /**
     * {@inheritdoc}
     *
     * @return static
     */
    public function withLink(LinkInterface $link)
    {
        $that = clone $this;
        $that->links[spl_object_id($link)] = $link;

        return $that;
    }

    /**
     * {@inheritdoc}
     *
     * @return static
     */
    public function withoutLink(LinkInterface $link)
    {
        $that = clone $this;
        unset($that->links[spl_object_id($link)]);

        return $that;
    }
}
