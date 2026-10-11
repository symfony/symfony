<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\WebLink\GenericLinkProvider;
use Symfony\Component\WebLink\Link;
use Twig\Attribute\AsTwigFunction;
use Twig\Extension\AbstractExtension;
use Twig\Extension\AttributeExtension;
use Twig\TwigFunction;

/**
 * Twig extension for the Symfony WebLink component.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final class WebLinkExtension extends AbstractExtension
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @deprecated since Symfony 8.2, register this class with "new AttributeExtension(WebLinkExtension::class)" instead
     */
    public function getFunctions(): array
    {
        trigger_deprecation('symfony/twig-bridge', '8.2', 'The "%s::getFunctions()" method is deprecated, register this class with "new %s(%s::class)" instead.', self::class, AttributeExtension::class, self::class);

        return [
            new TwigFunction('link', $this->link(...)),
            new TwigFunction('preload', $this->preload(...)),
            new TwigFunction('dns_prefetch', $this->dnsPrefetch(...)),
            new TwigFunction('preconnect', $this->preconnect(...)),
            new TwigFunction('prefetch', $this->prefetch(...)),
            new TwigFunction('prerender', $this->prerender(...)),
        ];
    }

    /**
     * Adds a "Link" HTTP header.
     *
     * @param string $rel        The relation type (e.g. "preload", "prefetch", or "dns-prefetch")
     * @param array  $attributes The attributes of this link (e.g. "['as' => true]", "['pr' => 0.5]")
     *
     * @return string The relation URI
     */
    #[AsTwigFunction('link')]
    public function link(string $uri, string $rel, array $attributes = []): string
    {
        if (!$request = $this->requestStack->getMainRequest()) {
            return $uri;
        }

        $link = new Link($rel, $uri);
        foreach ($attributes as $key => $value) {
            $link = $link->withAttribute($key, $value);
        }

        $linkProvider = $request->attributes->get('_links', new GenericLinkProvider());
        $request->attributes->set('_links', $linkProvider->withLink($link));

        return $uri;
    }

    /**
     * Preloads a resource.
     *
     * @param array $attributes The attributes of this link (e.g. "['as' => true]", "['crossorigin' => 'use-credentials']")
     *
     * @return string The path of the asset
     */
    #[AsTwigFunction('preload')]
    public function preload(string $uri, array $attributes = []): string
    {
        return $this->link($uri, 'preload', $attributes);
    }

    /**
     * Resolves a resource origin as early as possible.
     *
     * @param array $attributes The attributes of this link (e.g. "['as' => true]", "['pr' => 0.5]")
     *
     * @return string The path of the asset
     */
    #[AsTwigFunction('dns_prefetch')]
    public function dnsPrefetch(string $uri, array $attributes = []): string
    {
        return $this->link($uri, 'dns-prefetch', $attributes);
    }

    /**
     * Initiates a early connection to a resource (DNS resolution, TCP handshake, TLS negotiation).
     *
     * @param array $attributes The attributes of this link (e.g. "['as' => true]", "['pr' => 0.5]")
     *
     * @return string The path of the asset
     */
    #[AsTwigFunction('preconnect')]
    public function preconnect(string $uri, array $attributes = []): string
    {
        return $this->link($uri, 'preconnect', $attributes);
    }

    /**
     * Indicates to the client that it should prefetch this resource.
     *
     * @param array $attributes The attributes of this link (e.g. "['as' => true]", "['pr' => 0.5]")
     *
     * @return string The path of the asset
     */
    #[AsTwigFunction('prefetch')]
    public function prefetch(string $uri, array $attributes = []): string
    {
        return $this->link($uri, 'prefetch', $attributes);
    }

    /**
     * Indicates to the client that it should prerender this resource.
     *
     * This feature is deprecated and superseded by the Speculation Rules API.
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Attributes/rel/prerender
     *
     * @param array $attributes The attributes of this link (e.g. "['as' => true]", "['pr' => 0.5]")
     *
     * @return string The path of the asset
     */
    #[AsTwigFunction('prerender')]
    public function prerender(string $uri, array $attributes = []): string
    {
        return $this->link($uri, 'prerender', $attributes);
    }
}
