<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\ControllerMetadata;

use Symfony\Component\HttpKernel\ControllerMetadata\ControllerMetadata;
use Symfony\Component\HttpKernel\ControllerMetadata\ControllerMetadataFactoryInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ControllerMetadataUtil;
use Symfony\Component\Routing\RouterInterface;

/**
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
final class CachedControllerMetadataFactory implements ControllerMetadataFactoryInterface
{
    private $baseFactory;
    private $router;

    /**
     * Contains all previously requested metadata.
     *
     * @var ControllerMetadata[]
     */
    private $requested = [];
    private $cacheFile;

    /**
     * Contains serialized controller metedata for each pre-defined controller.
     *
     * @var string[]
     */
    private $warmed = [];

    public function __construct(ControllerMetadataFactoryInterface $baseFactory, RouterInterface $router, $cacheFile)
    {
        $this->baseFactory = $baseFactory;
        $this->router = $router;
        $this->cacheFile = $cacheFile;
    }

    /**
     * {@inheritdoc}
     */
    public function createControllerMetadata(callable $controller)
    {
        if (empty($this->warmed) && file_exists($this->cacheFile)) {
            $this->warmed = include $this->cacheFile;
        }

        if (null === ($logicalName = ControllerMetadataUtil::getControllerLogicalName($controller))) {
            return null;
        }

        $index = implode(':', $logicalName);

        // if already requested
        if (isset($this->requested[$index])) {
            return $this->requested[$index];
        }

        // if not requested but in cache
        if (isset($this->warmed[$index])) {
            return $this->requested[$index] = unserialize($this->warmed[$index]);
        }

        // not in cache but lets cache it for at least this request
        return $this->requested[$index] = $this->baseFactory->createControllerMetadata($controller);
    }
}
