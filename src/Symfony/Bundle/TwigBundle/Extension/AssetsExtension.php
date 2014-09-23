<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Extension;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RequestContext;

/**
 * Twig extension for Symfony assets helper
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class AssetsExtension extends \Twig_Extension
{
    private $container;
    private $context;

    public function __construct(ContainerInterface $container, RequestContext $requestContext = null)
    {
        $this->container = $container;
        $this->context = $requestContext;
    }

    /**
     * Returns a list of functions to add to the existing list.
     *
     * @return array An array of functions
     */
    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('asset', array($this, 'getAssetUrl')),
            new \Twig_SimpleFunction('assets_version', array($this, 'getAssetsVersion')),
        );
    }

    /**
     * Returns the public path of an asset.
     *
     * Absolute paths (i.e. http://...) are returned unmodified.
     *
     * @param string              $path        A public path
     * @param string              $packageName The name of the asset package to use
     * @param bool                $absolute    Whether to return an absolute URL or a relative one
     * @param string|bool|null    $version     A specific version
     *
     * @return string A public path which takes into account the base path and URL path
     */
    public function getAssetUrl($path, $packageName = null, $absolute = false, $version = null)
    {
        $url = $this->container->get('templating.helper.assets')->getUrl($path, $packageName, $version);

        if (!$absolute) {
            return $url;
        }

        return $this->ensureUrlIsAbsolute($url);
    }

    /**
     * Returns the version of the assets in a package.
     *
     * @param string $packageName
     *
     * @return int
     */
    public function getAssetsVersion($packageName = null)
    {
        return $this->container->get('templating.helper.assets')->getVersion($packageName);
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'assets';
    }

    /**
     * Ensures an URL is absolute, if possible.
     *
     * @param string $url The URL that has to be absolute
     *
     * @return string The absolute URL
     * @throws \RuntimeException
     */
    private function ensureUrlIsAbsolute($url)
    {
        if (false !== strpos($url, '://') || 0 === strpos($url, '//')) {
            return $url;
        }

        if (!$this->context) {
            throw new \RuntimeException('To generate an absolute URL for an asset, the Symfony Routing component is required.');
        }

        if ('' === $host = $this->context->getHost()) {
            return $url;
        }

        $scheme = $this->context->getScheme();
        $port = '';

        if ('http' === $scheme && 80 != $this->context->getHttpPort()) {
            $port = ':'.$this->context->getHttpPort();
        } elseif ('https' === $scheme && 443 != $this->context->getHttpsPort()) {
            $port = ':'.$this->context->getHttpsPort();
        }

        return $scheme.'://'.$host.$port.$url;
    }
}
