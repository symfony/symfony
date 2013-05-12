<?php

namespace Symfony\Component\Cache\Exception;

/**
 * Exception thrown when calling a method with an invalid argument.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class ObjectNotFoundException extends \InvalidArgumentException implements CacheExceptionInterface
{
}
