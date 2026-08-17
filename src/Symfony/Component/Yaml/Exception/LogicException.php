<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Yaml\Exception;

/**
 * Exception class thrown when a programming error is detected, such as a missing dependency.
 *
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 */
class LogicException extends \LogicException implements ExceptionInterface
{
}
