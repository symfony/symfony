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
 * Base UnexpectedValueException for the Security component.
 *
 * @author Oliver Hoff <oliver@hofff.com>
 */
class UnexpectedValueException extends \UnexpectedValueException implements ExceptionInterface
{
}
