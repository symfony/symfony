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
 * An asset package.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 */
class AssetPackage implements AssetPackageInterface
{
    private $baseUrls;
    private $version;

    /**
     * Constructor.
     *
     * @param array|string $baseUrls The domain URL or an array of domain URLs
     * @param string       $version  The version
     */
    public function __construct($baseUrls = array(), $version = null)
    {
        $this->baseUrls = array();
        $this->version = $version;

        if (!is_array($baseUrls)) {
            $baseUrls = (array) $baseUrls;
        }

        foreach ($baseUrls as $baseUrl) {
            $this->baseUrls[] = rtrim($baseUrl, '/');
        }
    }

    public function getVersion()
    {
        return $this->version;
    }

    public function getBaseUrl($path)
    {
        $count = count($this->baseUrls);

        if (0 === $count) {
            return '';
        }

        if (1 === $count) {
            return $this->baseUrls[0];
        }

        return $this->baseUrls[fmod(hexdec(substr(md5($path), 0, 10)), $count)];
    }
}
