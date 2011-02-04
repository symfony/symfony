<?php

namespace Symfony\Component\DependencyInjection\Configuration\Exception;

/**
 * This exception is thrown when a configuration path is overwritten from a
 * subsequent configuration file, but the entry node specifically forbids this.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ForbiddenOverwriteException extends InvalidConfigurationException
{
}