<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\DependencyInjection;

use Symfony\Component\Bundle\DependencyInjection\ConfigurableExtension as BaseConfigurableExtension;

/**
 * This extension sub-class provides first-class integration with the
 * Config/Definition Component.
 *
 * You can use this as base class if you
 *
 *    a) use the Config/Definition component for configuration
 *    b) your configuration class is named "Configuration" and
 *    c) the configuration class resides in the DependencyInjection sub-folder
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
abstract class ConfigurableExtension extends BaseConfigurableExtension
{
}
