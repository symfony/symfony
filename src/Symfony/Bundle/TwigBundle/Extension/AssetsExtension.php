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

@trigger_error('The '.__NAMESPACE__.'\AssetsExtension class is deprecated since version 2.7 and will be removed in 3.0. Use the Symfony\Bridge\Twig\Extension\AssetExtension class instead.', E_USER_DEPRECATED);

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RequestContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension for Symfony assets helper.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since 2.7, to be removed in 3.0. Use Symfony\Bridge\Twig\Extension\AssetExtension instead.
 */
class AssetsExtension extends AbstractExtension
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
            new TwigFunction('asset', array($this, 'getAssetUrl')),
            new TwigFunction('assets_version', array($this, 'getAssetsVersion')),
        );
    }

    /**
     * Returns the public path of an asset.
     *
     * Absolute paths (i.e. http://...) are returned unmodified.
     *
     * @param string           $path        A public path
     * @param string           $packageName The name of the asset package to use
     * @param bool             $absolute    Whether to return an absolute URL or a relative one
     * @param string|bool|null $version     A specific version
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
     * {@inheritdoc}
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
     *
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
