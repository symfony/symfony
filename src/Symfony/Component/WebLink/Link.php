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
use Symfony\Component\WebLink\Enum\LinkRelations;

class Link implements EvolvableLinkInterface
{
    // Relations defined in https://www.w3.org/TR/html5/links.html#links and applicable on link elements
    /** @deprecated since Symfony 6.2, use LinkRelations::ALTERNATE instead */
    public const REL_ALTERNATE = 'alternate';
    /** @deprecated since Symfony 6.2, use LinkRelations::AUTHOR instead */
    public const REL_AUTHOR = 'author';
    /** @deprecated since Symfony 6.2, use LinkRelations::HELP instead */
    public const REL_HELP = 'help';
    /** @deprecated since Symfony 6.2, use LinkRelations::ICON instead */
    public const REL_ICON = 'icon';
    /** @deprecated since Symfony 6.2, use LinkRelations::LICENSE instead */
    public const REL_LICENSE = 'license';
    /** @deprecated since Symfony 6.2, use LinkRelations::SEARCH instead */
    public const REL_SEARCH = 'search';
    /** @deprecated since Symfony 6.2, use LinkRelations::STYLESHEET instead */
    public const REL_STYLESHEET = 'stylesheet';
    /** @deprecated since Symfony 6.2, use LinkRelations::NEXT instead */
    public const REL_NEXT = 'next';
    /** @deprecated since Symfony 6.2, use LinkRelations::PREV instead */
    public const REL_PREV = 'prev';

    // Relation defined in https://www.w3.org/TR/preload/
    /** @deprecated since Symfony 6.2, use LinkRelations::PRELOAD instead */
    public const REL_PRELOAD = 'preload';

    // Relations defined in https://www.w3.org/TR/resource-hints/
    /** @deprecated since Symfony 6.2, use LinkRelations::DNS_PREFETCH instead */
    public const REL_DNS_PREFETCH = 'dns-prefetch';
    /** @deprecated since Symfony 6.2, use LinkRelations::PRECONNECT instead */
    public const REL_PRECONNECT = 'preconnect';
    /** @deprecated since Symfony 6.2, use LinkRelations::PREFETCH instead */
    public const REL_PREFETCH = 'prefetch';
    /** @deprecated since Symfony 6.2, use LinkRelations::PRERENDER instead */
    public const REL_PRERENDER = 'prerender';

    // Extra relations
    /** @deprecated since Symfony 6.2, use LinkRelations::MERCURE instead */
    public const REL_MERCURE = 'mercure';

    private string $href = '';

    /**
     * @var LinkRelations[]
     */
    private array $rel = [];

    /**
     * @var array<string, string|bool|string[]>
     */
    private array $attributes = [];

    public function __construct(string|LinkRelations|null $rel = null, string $href = '')
    {
        if (null !== $rel) {
            $relEnum = is_string($rel) ? LinkRelations::from($rel) : $rel;
            $this->rel[$relEnum->name] = $relEnum;
        }
        $this->href = $href;
    }

    public function getHref(): string
    {
        return $this->href;
    }

    public function isTemplated(): bool
    {
        return $this->hrefIsTemplated($this->href);
    }

    public function getRels(): array
    {
        return array_values(
            array_map(fn (LinkRelations $rel) => $rel->value, $this->rel)
        );
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function withHref(string|\Stringable $href): static
    {
        $that = clone $this;
        $that->href = $href;

        return $that;
    }

    public function withRel(string|LinkRelations $rel): static
    {
        $that = clone $this;
        $relEnum = is_string($rel) ? LinkRelations::from($rel) : $rel;

        $that->rel[$relEnum->name] = $relEnum;

        return $that;
    }

    public function withoutRel(string|LinkRelations $rel): static
    {
        $that = clone $this;
        $relEnum = is_string($rel) ? LinkRelations::from($rel) : $rel;

        unset($that->rel[$relEnum->name]);

        return $that;
    }

    public function withAttribute(string $attribute, string|\Stringable|int|float|bool|array $value): static
    {
        $that = clone $this;
        $that->attributes[$attribute] = $value;

        return $that;
    }

    public function withoutAttribute(string $attribute): static
    {
        $that = clone $this;
        unset($that->attributes[$attribute]);

        return $that;
    }

    private function hrefIsTemplated(string $href): bool
    {
        return str_contains($href, '{') || str_contains($href, '}');
    }
}
