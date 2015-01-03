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
 * Package that adds a base URL to asset URLs in addition to a version.
 *
 * As this package is aware of the current HTTP request, it can
 * determine the best base URL to use based on the current request
 * scheme.
 *
 *  * For HTTP request, it chooses between all base URLs;
 *  * For HTTPs requests, it chooses between HTTPs base URLs and relative protocol URLs
 *    or falls back to any base URL if no secure ones are available.
 *
 * When no request is available, it falls back to choose between all base URLs.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RequestUrlPackage extends UrlPackage
{
    private $requestStack;
    private $sslPackage;

    /**
     * @param RequestStack $request The request stack
     * @param string|array $baseUrls Base asset URLs
     * @param string       $version The version
     * @param string       $format  The version format
     */
    public function __construct(RequestStack $requestStack, $baseUrls = array(), $version = null, $format = null)
    {
        $this->requestStack = $requestStack;

        if (!is_array($baseUrls)) {
            $baseUrls = (array) $baseUrls;
        }

        if (!$baseUrls) {
            throw new \LogicException('You must provide at least one base URL.');
        }

        $sslUrls = $this->getSslUrls($baseUrls);

        parent::__construct($baseUrls, $version, $format);

        if ($sslUrls && $baseUrls !== $sslUrls) {
            $this->sslPackage = new UrlPackage($sslUrls, $version, $format);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getUrl($path, $version = null)
    {
        if (null === $this->sslPackage) {
            return parent::getUrl($path, $version);
        }

        if (($request = $this->requestStack->getCurrentRequest()) && $request->isSecure()) {
            return $this->sslPackage->getUrl($path, $version);
        }

        return parent::getUrl($path, $version);
    }

    private function getSslUrls($urls)
    {
        $sslUrls = array();
        foreach ($urls as $url) {
            if ('https://' === substr($url, 0, 8) || '//' === substr($url, 0, 2)) {
                $sslUrls[] = $url;
            } elseif ('http://' !== substr($url, 0, 7)) {
                throw new \InvalidArgumentException(sprintf('"%s" is not a valid URL', $url));
            }
        }

        return $sslUrls;
    }
}
