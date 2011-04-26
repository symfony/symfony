<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Exception;

/**
 * UnexpectedValueException for the Serializer component.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class UnexpectedValueException extends \UnexpectedValueException implements Exception
{
}