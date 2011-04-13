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
    private $versionFormat;

    /**
     * Constructor.
     *
     * @param string|array $baseUrls      The asset base URL(s)
     * @param string       $version       The asset version string
     * @param string       $versionFormat The format to use when adding version
     */
    public function __construct($baseUrls = array(), $version = null, $versionFormat = '%s?%s')
    {
        $this->setBaseUrls((array) $baseUrls);
        $this->version = $version;
        $this->versionFormat = $versionFormat;
    }

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

    public function setBaseUrls(array $baseUrls = array())
    {
        if (!is_array($baseUrls)) {
            $baseUrls = (array) $baseUrls;
        }

        $this->baseUrls = array();
        foreach ($baseUrls as $baseUrl) {
            $this->baseUrls[] = rtrim($baseUrl, '/');
        }
    }

    public function getVersion()
    {
        return $this->version;
    }

    public function setVersion($version)
    {
        $this->version = $version;
    }

    public function versionize($path)
    {
        if (null === $this->version) {
            return $path;
        }

        if (strlen($path) && '/' == $path[0]) {
            $prepend = '/';
            $path = substr($path, 1);
        } else {
            $prepend = '';
        }

        return $prepend.sprintf($this->versionFormat, $path, $this->version);
    }
}
