<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Config\Definition\Exception;

/**
 * This exception is thrown when a configuration path is overwritten from a
 * subsequent configuration file, but the entry node specifically forbids this.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ForbiddenOverwriteException extends InvalidConfigurationException
{
}
