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
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension for the Symfony WebLink component.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final class WebLinkExtension extends AbstractExtension
{
    private RequestStack $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions(): array
    {
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
     * @param string $rel        The relation type (e.g. "preload", "prefetch", "prerender" or "dns-prefetch")
     * @param array  $attributes The attributes of this link (e.g. "['as' => true]", "['pr' => 0.5]")
     *
     * @return string The relation URI
     */
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
    public function prefetch(string $uri, array $attributes = []): string
    {
        return $this->link($uri, 'prefetch', $attributes);
    }

    /**
     * Indicates to the client that it should prerender this resource .
     *
     * @param array $attributes The attributes of this link (e.g. "['as' => true]", "['pr' => 0.5]")
     *
     * @return string The path of the asset
     */
    public function prerender(string $uri, array $attributes = []): string
    {
        return $this->link($uri, 'prerender', $attributes);
    }
}
