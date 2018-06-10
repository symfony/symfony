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

use Fig\Link\GenericLinkProvider;
use Fig\Link\Link;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension for the Symfony WebLink component.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class WebLinkExtension extends AbstractExtension
{
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return array(
            new TwigFunction('link', array($this, 'link')),
            new TwigFunction('preload', array($this, 'preload')),
            new TwigFunction('dns_prefetch', array($this, 'dnsPrefetch')),
            new TwigFunction('preconnect', array($this, 'preconnect')),
            new TwigFunction('prefetch', array($this, 'prefetch')),
            new TwigFunction('prerender', array($this, 'prerender')),
        );
    }

    /**
     * Adds a "Link" HTTP header.
     *
     * @param string $uri        The relation URI
     * @param string $rel        The relation type (e.g. "preload", "prefetch", "prerender" or "dns-prefetch")
     * @param array  $attributes The attributes of this link (e.g. "array('as' => true)", "array('pr' => 0.5)")
     *
     * @return string The relation URI
     */
    public function link($uri, $rel, array $attributes = array())
    {
        if (!$request = $this->requestStack->getMasterRequest()) {
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
     * @param string $uri        A public path
     * @param array  $attributes The attributes of this link (e.g. "array('as' => true)", "array('crossorigin' => 'use-credentials')")
     *
     * @return string The path of the asset
     */
    public function preload($uri, array $attributes = array())
    {
        return $this->link($uri, 'preload', $attributes);
    }

    /**
     * Resolves a resource origin as early as possible.
     *
     * @param string $uri        A public path
     * @param array  $attributes The attributes of this link (e.g. "array('as' => true)", "array('pr' => 0.5)")
     *
     * @return string The path of the asset
     */
    public function dnsPrefetch($uri, array $attributes = array())
    {
        return $this->link($uri, 'dns-prefetch', $attributes);
    }

    /**
     * Initiates a early connection to a resource (DNS resolution, TCP handshake, TLS negotiation).
     *
     * @param string $uri        A public path
     * @param array  $attributes The attributes of this link (e.g. "array('as' => true)", "array('pr' => 0.5)")
     *
     * @return string The path of the asset
     */
    public function preconnect($uri, array $attributes = array())
    {
        return $this->link($uri, 'preconnect', $attributes);
    }

    /**
     * Indicates to the client that it should prefetch this resource.
     *
     * @param string $uri        A public path
     * @param array  $attributes The attributes of this link (e.g. "array('as' => true)", "array('pr' => 0.5)")
     *
     * @return string The path of the asset
     */
    public function prefetch($uri, array $attributes = array())
    {
        return $this->link($uri, 'prefetch', $attributes);
    }

    /**
     * Indicates to the client that it should prerender this resource .
     *
     * @param string $uri        A public path
     * @param array  $attributes The attributes of this link (e.g. "array('as' => true)", "array('pr' => 0.5)")
     *
     * @return string The path of the asset
     */
    public function prerender($uri, array $attributes = array())
    {
        return $this->link($uri, 'prerender', $attributes);
    }
}
