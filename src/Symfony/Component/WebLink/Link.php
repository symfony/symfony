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

use Psr\Link\EvolvableLinkInterface;

class Link implements EvolvableLinkInterface
{
    // Relations defined in https://www.w3.org/TR/html5/links.html#links and applicable on link elements
    public const REL_ALTERNATE = 'alternate';
    public const REL_AUTHOR = 'author';
    public const REL_HELP = 'help';
    public const REL_ICON = 'icon';
    public const REL_LICENSE = 'license';
    public const REL_SEARCH = 'search';
    public const REL_STYLESHEET = 'stylesheet';
    public const REL_NEXT = 'next';
    public const REL_PREV = 'prev';

    // Relation defined in https://www.w3.org/TR/preload/
    public const REL_PRELOAD = 'preload';

    // Relations defined in https://www.w3.org/TR/resource-hints/
    public const REL_DNS_PREFETCH = 'dns-prefetch';
    public const REL_PRECONNECT = 'preconnect';
    public const REL_PREFETCH = 'prefetch';
    public const REL_PRERENDER = 'prerender';

    // Extra relations
    public const REL_MERCURE = 'mercure';

    private $href = '';

    /**
     * @var string[]
     */
    private $rel = [];

    /**
     * @var string[]
     */
    private $attributes = [];

    public function __construct(string $rel = null, string $href = '')
    {
        if (null !== $rel) {
            $this->rel[$rel] = $rel;
        }
        $this->href = $href;
    }

    /**
     * {@inheritdoc}
     */
    public function getHref(): string
    {
        return $this->href;
    }

    /**
     * {@inheritdoc}
     */
    public function isTemplated(): bool
    {
        return $this->hrefIsTemplated($this->href);
    }

    /**
     * {@inheritdoc}
     */
    public function getRels(): array
    {
        return array_values($this->rel);
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * {@inheritdoc}
     *
     * @return static
     */
    public function withHref($href)
    {
        $that = clone $this;
        $that->href = $href;
        $that->templated = $this->hrefIsTemplated($href);

        return $that;
    }

    /**
     * {@inheritdoc}
     *
     * @return static
     */
    public function withRel($rel)
    {
        $that = clone $this;
        $that->rel[$rel] = $rel;

        return $that;
    }

    /**
     * {@inheritdoc}
     *
     * @return static
     */
    public function withoutRel($rel)
    {
        $that = clone $this;
        unset($that->rel[$rel]);

        return $that;
    }

    /**
     * {@inheritdoc}
     *
     * @return static
     */
    public function withAttribute($attribute, $value)
    {
        $that = clone $this;
        $that->attributes[$attribute] = $value;

        return $that;
    }

    /**
     * {@inheritdoc}
     *
     * @return static
     */
    public function withoutAttribute($attribute)
    {
        $that = clone $this;
        unset($that->attributes[$attribute]);

        return $that;
    }

    private function hrefIsTemplated(string $href): bool
    {
        return false !== strpos($href, '{') || false !== strpos($href, '}');
    }
}
