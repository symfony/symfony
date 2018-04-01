<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection\Exception;

use Psr\Container\ContainerExceptionInterface;

/**
 * Base ExceptionInterface for Dependency Injection component.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 * @author Bulat Shakirzyanov <bulat@theopenskyproject.com>
 */
interface ExceptionInterface extends ContainerExceptionInterface
{
}
