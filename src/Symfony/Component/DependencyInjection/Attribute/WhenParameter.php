<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Attribute;

/**
 * An attribute to register this class as a service only when a container parameter has the given value.
 *
 * The condition fails when the parameter is not defined. Parameters resolved from
 * environment variables cannot be used because their value is not known at compile time.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class WhenParameter
{
    /**
     * @param string $name  The name of the container parameter
     * @param mixed  $value The value the parameter must be equal to (defaults to true)
     */
    public function __construct(
        public string $name,
        public mixed $value = true,
    ) {
    }
}
