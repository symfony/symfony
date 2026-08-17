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
 * Holds the URI logic shared by the classic and the native URI elements.
 *
 * The using class declares the $node property with the element type it accepts,
 * so that each of them keeps an exact signature.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
trait UriElementTrait
{
    /**
     * Gets the method associated with this link.
     */
    public function getMethod(): string
    {
        return $this->method ?? 'GET';
    }

    /**
     * Gets the URI associated with this link.
     */
    public function getUri(): string
    {
        return UriResolver::resolve($this->getRawUri(), $this->currentUri);
    }

    /**
     * Returns raw URI data.
     */
    abstract protected function getRawUri(): string;

    /**
     * Returns the canonicalized URI path (see RFC 3986, section 5.2.4).
     *
     * @param string $path URI path
     */
    protected function canonicalizePath(string $path): string
    {
        if ('' === $path || '/' === $path) {
            return $path;
        }

        if (str_ends_with($path, '.')) {
            $path .= '/';
        }

        $output = [];

        foreach (explode('/', $path) as $segment) {
            if ('..' === $segment) {
                array_pop($output);
            } elseif ('.' !== $segment) {
                $output[] = $segment;
            }
        }

        return implode('/', $output);
    }

    /**
     * @throws \InvalidArgumentException if the URI is relative and no absolute base URI is available
     */
    private function assertUriIsResolvable(): void
    {
        $elementUriIsRelative = !parse_url(trim($this->getRawUri()), \PHP_URL_SCHEME);
        $baseUriIsAbsolute = null !== $this->currentUri && \in_array(strtolower(substr($this->currentUri, 0, 4)), ['http', 'file'], true);

        if ($elementUriIsRelative && !$baseUriIsAbsolute) {
            throw new \InvalidArgumentException(\sprintf('The URL of the element is relative, so you must define its base URI passing an absolute URL to the constructor of the "%s" class ("%s" was passed).', __CLASS__, $this->currentUri));
        }
    }
}
