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
    private array $links = [];

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

    public function getLinks(): array
    {
        return array_values($this->links);
    }

    public function getLinksByRel(string $rel): array
    {
        $links = [];

        foreach ($this->links as $link) {
            if (\in_array($rel, $link->getRels(), true)) {
                $links[] = $link;
            }
        }

        return $links;
    }

    public function withLink(LinkInterface $link): static
    {
        $that = clone $this;
        $that->links[spl_object_id($link)] = $link;

        return $that;
    }

    public function withoutLink(LinkInterface $link): static
    {
        $that = clone $this;
        unset($that->links[spl_object_id($link)]);

        return $that;
    }
}
