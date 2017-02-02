<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\ControllerMetadata;

/**
 * Builds controller data.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
interface ControllerMetadataFactoryInterface
{
    /**
     * @param callable $controller The controller to resolve the arguments for
     *
     * @return ControllerMetadata|null
     */
    public function createControllerMetadata(callable $controller);
}
