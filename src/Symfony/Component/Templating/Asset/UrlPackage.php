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

@trigger_error('The Symfony\Component\Templating\Asset\UrlPackage is deprecated since Symfony 2.7 and will be removed in 3.0. Use the Asset component instead.', E_USER_DEPRECATED);

/**
 * The URL packages adds a version and a base URL to asset URLs.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 *
 * @deprecated since 2.7, will be removed in 3.0. Use the Asset component instead.
 */
class UrlPackage extends Package
{
    private $baseUrls;

    /**
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

    /**
     * {@inheritdoc}
     */
    public function getUrl($path, $version = null)
    {
        if (false !== strpos($path, '://') || 0 === strpos($path, '//')) {
            return $path;
        }

        $url = $this->applyVersion($path, $version);

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
        switch ($count = count($this->baseUrls)) {
            case 0:
                return '';

            case 1:
                return $this->baseUrls[0];

            default:
                return $this->baseUrls[fmod(hexdec(substr(hash('sha256', $path), 0, 10)), $count)];
        }
    }
}
