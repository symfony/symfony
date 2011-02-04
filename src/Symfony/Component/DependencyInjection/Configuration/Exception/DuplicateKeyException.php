<?php

namespace Symfony\Component\DependencyInjection\Configuration\Exception;

/**
 * This exception is thrown whenever the key of an array is not unique. This can
 * only be the case if the configuration is coming from an XML file.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class DuplicateKeyException extends InvalidConfigurationException
{
}
