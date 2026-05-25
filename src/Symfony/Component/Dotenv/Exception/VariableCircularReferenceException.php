<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Dotenv\Exception;

/**
 * Thrown when there are too many levels of variable indirection in env vars.
 *
 * @author Pascal CESCON <pascal.cescon@gmail.com>
 */
final class VariableCircularReferenceException extends \LogicException implements ExceptionInterface
{
}
