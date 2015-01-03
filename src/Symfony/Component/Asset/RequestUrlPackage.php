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

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RequestUrlPackage implements PackageInterface
{
    private $version;
    private $requestStack;
    private $package;
    private $sslPackage;

    /**
     * @param RequestStack $request The request stack
     * @param string       $version The version
     * @param string       $format  The version format
     */
    public function __construct(RequestStack $requestStack, $baseUrls = array(), $version = null, $format = null)
    {
        $this->requestStack = $requestStack;
        $this->version = $version;

        list($allUrls, $sslUrls) = $this->organizeUrls($baseUrls);

        if (!$allUrls) {
            $this->package = new RequestPathPackage($requestStack, $version, $format);
        } elseif ($allUrls === $sslUrls) {
            $this->package = new UrlPackage($allUrls, $version, $format);
        } else {
            $this->package = new UrlPackage($allUrls, $version, $format);

            if ($sslUrls) {
                $this->sslPackage = new UrlPackage($sslUrls, $version, $format);
            } else {
                $this->sslPackage = new RequestPathPackage($version, $format);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * {@inheritdoc}
     */
    public function getUrl($path, $version = null)
    {
        if (null === $this->sslPackage) {
            return $this->package->getUrl($path, $version);
        }

        if (($request = $this->requestStack->getCurrentRequest()) && $request->isSecure()) {
            return $this->sslPackage->getUrl($path, $version);
        }

        return $this->package->getUrl($path, $version);
    }

    private function organizeUrls($urls)
    {
        $sslUrls =  isset($urls['ssl']) ? $urls['ssl'] : array();
        $allUrls = array_merge(isset($urls['http']) ? $urls['http'] : array(), $sslUrls);
        foreach ($urls as $i => $url) {
            if (!is_integer($i)) {
                continue;
            }

            $allUrls[] = $url;
            if (0 === strpos($url, 'https://') || 0 === strpos($url, '//')) {
                $sslUrls[] = $url;
            }
        }

        return array($allUrls, $sslUrls);
    }
}
