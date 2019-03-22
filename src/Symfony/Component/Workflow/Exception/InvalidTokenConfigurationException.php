<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Exception;

/**
 * Thrown by GuardListener when there is no token set, but guards are placed on a transition.
 *
 * @author Matt Johnson <matj1985@gmail.com>
 */
class InvalidTokenConfigurationException extends LogicException
{
}
