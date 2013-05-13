<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Exception;

/**
 * Exception thrown when calling a method with an object that does not make sense.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class BadMethodCallException extends \BadMethodCallException implements CacheExceptionInterface
{
}
