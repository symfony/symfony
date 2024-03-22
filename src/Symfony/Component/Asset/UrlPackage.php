<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset;

use Symfony\Component\Asset\Context\ContextInterface;
use Symfony\Component\Asset\Exception\InvalidArgumentException;
use Symfony\Component\Asset\Exception\LogicException;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;

/**
 * Package that adds a base URL to asset URLs in addition to a version.
 *
 * The package allows to use more than one base URLs in which case
 * it randomly chooses one for each asset; it also guarantees that
 * any given path will always use the same base URL to be nice with
 * HTTP caching mechanisms.
 *
 * When the request context is available, this package can choose the
 * best base URL to use based on the current request scheme:
 *
 *  * For HTTP request, it chooses between all base URLs;
 *  * For HTTPs requests, it chooses between HTTPs base URLs and relative protocol URLs
 *    or falls back to any base URL if no secure ones are available.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class UrlPackage extends Package
{
    private array $baseUrls = [];
    private ?self $sslPackage = null;

    /**
     * @param string|string[] $baseUrls Base asset URLs
     */
    public function __construct(string|array $baseUrls, VersionStrategyInterface $versionStrategy, ?ContextInterface $context = null)
    {
        parent::__construct($versionStrategy, $context);

        if (!\is_array($baseUrls)) {
            $baseUrls = (array) $baseUrls;
        }

        if (!$baseUrls) {
            throw new LogicException('You must provide at least one base URL.');
        }

        foreach ($baseUrls as $baseUrl) {
            $this->baseUrls[] = rtrim($baseUrl, '/');
        }

        $sslUrls = $this->getSslUrls($baseUrls);

        if ($sslUrls && $baseUrls !== $sslUrls) {
            $this->sslPackage = new self($sslUrls, $versionStrategy);
        }
    }

    public function getUrl(string $path): string
    {
        if ($this->isAbsoluteUrl($path)) {
            return $path;
        }

        if (null !== $this->sslPackage && $this->getContext()->isSecure()) {
            return $this->sslPackage->getUrl($path);
        }

        $url = $this->getVersionStrategy()->applyVersion($path);

        if ($this->isAbsoluteUrl($url)) {
            return $url;
        }

        if ($url && '/' != $url[0]) {
            $url = '/'.$url;
        }

        return $this->getBaseUrl($path).$url;
    }

    /**
     * Returns the base URL for a path.
     */
    public function getBaseUrl(string $path): string
    {
        if (1 === \count($this->baseUrls)) {
            return $this->baseUrls[0];
        }

        return $this->baseUrls[$this->chooseBaseUrl($path)];
    }

    /**
     * Determines which base URL to use for the given path.
     *
     * Override this method to change the default distribution strategy.
     * This method should always return the same base URL index for a given path.
     */
    protected function chooseBaseUrl(string $path): int
    {
        return abs(crc32($path)) % \count($this->baseUrls);
    }

    private function getSslUrls(array $urls): array
    {
        $sslUrls = [];
        foreach ($urls as $url) {
            if (str_starts_with($url, 'https://') || str_starts_with($url, '//') || '' === $url) {
                $sslUrls[] = $url;
            } elseif (null === parse_url($url, \PHP_URL_SCHEME)) {
                throw new InvalidArgumentException(sprintf('"%s" is not a valid URL.', $url));
            }
        }

        return $sslUrls;
    }
}
