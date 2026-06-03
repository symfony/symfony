<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Exception;

/**
 * Base LogicException for the Security component.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
class LogicException extends \LogicException implements ExceptionInterface
{
}
