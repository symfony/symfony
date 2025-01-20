<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Exception;

use Psr\Container\NotFoundExceptionInterface;

/**
 * This exception is thrown when an existent parameter with an empty value is used.
 *
 * @author Yonel Ceruto <open@yceruto.dev>
 */
class EmptyParameterValueException extends InvalidArgumentException implements NotFoundExceptionInterface
{
}
