<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Workflow\Exception;

/**
 * Thrown by GuardListener when there is no token set, but guards are placed on a transition.
 *
 * @author Matt Johnson <matj1985@gmail.com>
 */
class InvalidTokenConfigurationException extends LogicException implements ExceptionInterface
{
}
