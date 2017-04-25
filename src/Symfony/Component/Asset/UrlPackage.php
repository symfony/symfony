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
 *  * For HTTP request:
 *    * if $isStrictHttp is set to false, it chooses between all base URLs
 *    * if $isStrictHttp is set to true, it will only use HTTP base URLs and relative protocol URLs
 *      or falls back to any base URL if no secure ones are available;
 *  * For HTTPs requests, it chooses between HTTPs base URLs and relative protocol URLs
 *    or falls back to any base URL if no secure ones are available.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class UrlPackage extends Package
{
    /** @var array $baseUrls */
    private $baseUrls = array();
    /** @var string[] $baseSecureUrls */
    private $baseSecureUrls = array();
    /** @var string[] $baseUnsecureUrls */
    private $baseUnsecureUrls = array();
    /** @var string[] $baseFullUrls */
    private $baseFullUrls = array();
    /** @var bool $isStrictHttp */
    private $isStrictHttp;

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

        $this->isStrictHttp = $isStrictHttp;

        $this->prepareBaseUrl($this->baseUrls, $isStrictHttp);
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

        $this->setCurrentBaseUrls();

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
     * Set the baseUrls var depending on the context.
     */
    private function setCurrentBaseUrls()
    {
        if (!empty($this->baseSecureUrls) && $this->getContext()->isSecure()) {
            $this->baseUrls = $this->baseSecureUrls;
        } elseif (!empty($this->baseUnsecureUrls) && $this->isStrictHttp) {
            $this->baseUrls = $this->baseUnsecureUrls;
        } else {
            $this->baseUrls = $this->baseFullUrls;
        }
    }

    /**
     * Split urls in three categories: secure urls, unsecure urls and all urls
     * Some url can be found in both categories (// & https depending on $isStrictHttp option).
     *
     * @param array $urls
     * @param bool  $isStrictHttp
     */
    private function prepareBaseUrl(array $urls, $isStrictHttp)
    {
        $this->baseFullUrls = $urls;

        foreach ($urls as $url) {
            if ('https://' === substr($url, 0, 8)) {
                $this->baseSecureUrls[] = $url;
                if ($isStrictHttp === false) {
                    $this->baseUnsecureUrls[] = $url;
                }
            } elseif ('http://' === substr($url, 0, 7)) {
                $this->baseUnsecureUrls[] = $url;
            } elseif ('//' === substr($url, 0, 2)) {
                $this->baseUnsecureUrls[] = $url;
                $this->baseSecureUrls[] = $url;
            } else {
                throw new InvalidArgumentException(sprintf('"%s" is not a valid URL', $url));
            }
        }
    }
}
