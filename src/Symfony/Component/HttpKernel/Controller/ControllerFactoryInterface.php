<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Controller;

/**
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
interface ControllerFactoryInterface
{
    /**
     * Create a controller callable based on a string.
     *
     * @param string $controller
     *
     * @return callable
     */
    public function createFromString($controller);
}
