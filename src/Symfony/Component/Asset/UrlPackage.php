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

    /**
     * @param string|string[]          $baseUrls        Base asset URLs
     * @param VersionStrategyInterface $versionStrategy The version strategy
     * @param ContextInterface|null    $context         Context
     * @param bool                     $isStrictHttp    Is http strict, or does it allow https on http pages
     */
    public function __construct($baseUrls, VersionStrategyInterface $versionStrategy, ContextInterface $context = null, $isStrictHttp = false)
    {
        parent::__construct($versionStrategy, $context);

        if (!is_array($baseUrls)) {
            $baseUrls = (array) $baseUrls;
        }

        if (!$baseUrls) {
            throw new LogicException('You must provide at least one base URL.');
        }

        foreach ($baseUrls as $baseUrl) {
            $this->baseUrls[] = rtrim($baseUrl, '/');
        }

        $urlList = $this->splitBaseUrl($this->baseUrls, $isStrictHttp);
        if (!empty($urlList['httpsUrl']) && $this->getContext()->isSecure()) {
            $this->baseUrls = $urlList['httpsUrl'];
        } else {
            $this->baseUrls = $urlList['httpUrl'];
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
     * This method should always return the same base URL index for a given path.
     *
     * @param string $path
     *
     * @return int The base URL index for the given path
     */
    protected function chooseBaseUrl($path)
    {
        return (int) fmod(hexdec(substr(hash('sha256', $path), 0, 10)), count($this->baseUrls));
    }

    /**
     * Split urls in two categories: http & https urls
     * Some url can be found in both categories (// & https depending on $isStrictHttp option).
     *
     * @param array $urls
     * @param bool  $isStrictHttp
     *
     * @return array
     */
    protected function splitBaseUrl(array $urls, $isStrictHttp)
    {
        $urlList = array('httpUrl' => array(), 'httpsUrl' => array());

        foreach ($urls as $url) {
            if ('https://' === substr($url, 0, 8)) {
                $urlList['httpsUrl'][] = $url;
                if ($isStrictHttp === false) {
                    $urlList['httpUrl'][] = $url;
                }
            } elseif ('http://' === substr($url, 0, 7)) {
                $urlList['httpUrl'][] = $url;
            } elseif ('//' === substr($url, 0, 2)) {
                $urlList['httpUrl'][] = $url;
                $urlList['httpsUrl'][] = $url;
            } else {
                throw new InvalidArgumentException(sprintf('"%s" is not a valid URL', $url));
            }
        }

        return $urlList;
    }
}
