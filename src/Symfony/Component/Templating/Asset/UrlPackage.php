<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating\Asset;

/**
 * The URL packages adds a version and a base URL to asset URLs.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 */
class UrlPackage extends Package
{
    private $baseUrls;

    /**
     * Constructor.
     *
     * @param string|array $baseUrls Base asset URLs
     * @param string       $version  The package version
     * @param string       $format   The format used to apply the version
     */
    public function __construct($baseUrls = array(), $version = null, $format = null)
    {
        parent::__construct($version, $format);

        if (!is_array($baseUrls)) {
            $baseUrls = (array) $baseUrls;
        }

        $this->baseUrls = array();
        foreach ($baseUrls as $baseUrl) {
            $this->baseUrls[] = rtrim($baseUrl, '/');
        }
    }

    public function getUrl($path)
    {
        if (false !== strpos($path, '://') || 0 === strpos($path, '//')) {
            return $path;
        }

        $url = $this->applyVersion($path);

        if ($url && '/' != $url[0]) {
            $url = '/'.$url;
        }

        return $this->getBaseUrl($path).$url;
    }

    /**
     * Returns the base URL for a path.
     *
     * @return string The base URL
     */
    public function getBaseUrl($path)
    {
        switch ($count = count($this->baseUrls)) {
            case 0:
                return '';

            case 1:
                return $this->baseUrls[0];

            default:
                return $this->baseUrls[fmod(hexdec(substr(md5($path), 0, 10)), $count)];
        }
    }
}
