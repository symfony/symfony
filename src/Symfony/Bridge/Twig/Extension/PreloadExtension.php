<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Symfony\Component\Preload\PreloadManagerInterface;

/**
 * Twig extension for the Symfony Preload component.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class PreloadExtension extends \Twig_Extension
{
    private $preloadManager;

    public function __construct(PreloadManagerInterface $preloadManager)
    {
        $this->preloadManager = $preloadManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('preload', array($this, 'preload')),
        );
    }

    /**
     * Preloads an asset.
     *
     * @param string $path   A public path
     * @param string $as     A valid destination according to https://fetch.spec.whatwg.org/#concept-request-destination
     * @param bool   $nopush If this asset should not be pushed over HTTP/2
     *
     * @return string The path of the asset
     */
    public function preload($path, $as = '', $nopush = false)
    {
        $this->preloadManager->addResource($path, $as, $nopush);

        return $path;
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'preload';
    }
}
