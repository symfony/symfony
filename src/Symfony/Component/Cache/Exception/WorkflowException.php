<?php

namespace Symfony\Component\Cache\Exception;

/**
 * Exception thrown when calling methods in the wrong order.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class WorkflowException extends \LogicException implements CacheExceptionInterface
{
}
