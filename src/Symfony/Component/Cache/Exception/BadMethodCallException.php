<?php

namespace Symfony\Component\Cache\Exception;

/**
 * Exception thrown when calling a method with an object that does not make sense.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class BadMethodCallException extends \BadMethodCallException implements CacheExceptionInterface
{
}
