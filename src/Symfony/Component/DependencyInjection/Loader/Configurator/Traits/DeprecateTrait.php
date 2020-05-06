<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator\Traits;

use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

trait DeprecateTrait
{
    /**
     * Whether this definition is deprecated, that means it should not be called anymore.
     *
     * @param string $package The name of the composer package that is triggering the deprecation
     * @param string $version The version of the package that introduced the deprecation
     * @param string $message The deprecation message to use
     *
     * @return $this
     *
     * @throws InvalidArgumentException when the message template is invalid
     */
    final public function deprecate(/* string $package, string $version, string $message */): self
    {
        $args = \func_get_args();
        $package = $version = $message = '';

        if (\func_num_args() < 3) {
            trigger_deprecation('symfony/dependency-injection', '5.1', 'The signature of method "%s()" requires 3 arguments: "string $package, string $version, string $message", not defining them is deprecated.', __METHOD__);

            $message = (string) ($args[0] ?? null);
        } else {
            $package = (string) $args[0];
            $version = (string) $args[1];
            $message = (string) $args[2];
        }

        $this->definition->setDeprecated($package, $version, $message);

        return $this;
    }
}
