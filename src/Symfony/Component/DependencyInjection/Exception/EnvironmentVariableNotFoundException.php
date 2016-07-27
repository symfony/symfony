<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Exception;

/**
 * This exception is thrown when an environment variable was not found.
 *
 * @author Magnus Nordlander <magnus@fervo.se>
 */
class EnvironmentVariableNotFoundException extends InvalidArgumentException
{
    public function __construct($name)
    {
        parent::__construct(sprintf('You have requested a non-existent environment variable "%s".', $name));
    }
}
