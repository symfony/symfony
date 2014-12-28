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

use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;
use Symfony\Component\Asset\Exception\InvalidArgumentException;
use Symfony\Component\Asset\Exception\LogicException;

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
    private $baseUrls = array();
    private $sslUrls;
    private $sslPackage;

    /**
     * @param string|array             $baseUrls        Base asset URLs
     * @param VersionStrategyInterface $versionStrategy The version strategy
     */
    public function __construct($baseUrls = array(), VersionStrategyInterface $versionStrategy)
    {
        parent::__construct($versionStrategy);

        if (!is_array($baseUrls)) {
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
            $this->sslPackage = new UrlPackage($sslUrls, $versionStrategy);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getUrl($path)
    {
        if ($this->isAbsoluteUrl($path)) {
            return $path;
        }

        if (null !== $this->sslPackage && ($context = $this->getContext()) && $context->isSecure()) {
            return $this->sslPackage->getUrl($path);
        }

        $url = $this->getVersionStrategy()->applyVersion($path);

        if ($url && '/' != $url[0]) {
            $url = '/'.$url;
        }

        return $this->getBaseUrl($path).$url;
    }

    /**
     * Returns the base URL for a path.
     *
     * @param string $path
     *
     * @return string The base URL
     */
    public function getBaseUrl($path)
    {
        if (1 === count($this->baseUrls)) {
            return $this->baseUrls[0];
        }

        return $this->baseUrls[$this->chooseBaseUrl($path)];
    }

    /**
     * Determines which base URL to use for the given path.
     *
     * Override this method to change the default distribution strategy.
     * This method should always return the same base URL for a given path.
     *
     * @param string $path
     *
     * @return string The base URL for the given path
     */
    protected function chooseBaseUrl($path)
    {
        return fmod(hexdec(substr(hash('sha256', $path), 0, 10)), count($this->baseUrls));
    }

    private function getSslUrls($urls)
    {
        $sslUrls = array();
        foreach ($urls as $url) {
            if ('https://' === substr($url, 0, 8) || '//' === substr($url, 0, 2)) {
                $sslUrls[] = $url;
            } elseif ('http://' !== substr($url, 0, 7)) {
                throw new InvalidArgumentException(sprintf('"%s" is not a valid URL', $url));
            }
        }

        return $sslUrls;
    }
}
