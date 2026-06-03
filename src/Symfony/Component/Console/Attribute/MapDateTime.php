<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Attribute;

/**
 * Defines how a DateTime parameter should be resolved from command input.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class MapDateTime
{
    /**
     * @param string|null $format   The DateTime format (@see https://php.net/datetime.format)
     * @param string|null $argument The argument name to read from (defaults to parameter name)
     * @param string|null $option   The option name to read from (mutually exclusive with $argument)
     */
    public function __construct(
        public readonly ?string $format = null,
        public readonly ?string $argument = null,
        public readonly ?string $option = null,
    ) {
        if ($argument && $option) {
            throw new \LogicException('MapDateTime cannot specify both argument and option.');
        }
    }
}
