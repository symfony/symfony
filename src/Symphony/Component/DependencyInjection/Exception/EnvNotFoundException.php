<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection\Exception;

/**
 * This exception is thrown when an environment variable is not found.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class EnvNotFoundException extends InvalidArgumentException
{
    public function __construct(string $name)
    {
        parent::__construct(sprintf('Environment variable not found: "%s".', $name));
    }
}
