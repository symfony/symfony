<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\CacheWarmer;

use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ControllerMetadataFactoryInterface;

/**
 * Generates the cache for controller metadata.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
final class ControllerMetadataCacheWarmer implements CacheWarmerInterface
{
    private $controllerMetadataFactory;

    public function __construct(ControllerMetadataFactoryInterface $controllerMetadataFactory)
    {
        $this->controllerMetadataFactory = $controllerMetadataFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function warmUp($cacheDir)
    {
        if ($this->controllerMetadataFactory instanceof WarmableInterface) {
            $this->controllerMetadataFactory->warmUp($cacheDir);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isOptional()
    {
        return true;
    }
}
