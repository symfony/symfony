<?php

namespace Symfony\Component\DependencyInjection\Configuration\Exception;

/**
 * This exception is usually not encountered by the end-user, but only used
 * internally to signal the parent scope to unset a key.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class UnsetKeyException extends Exception
{
}