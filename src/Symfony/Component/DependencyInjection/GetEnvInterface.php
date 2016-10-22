<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection;

/**
 * The GetEnvInterface is implemented by objects that manage environment-like variables.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface GetEnvInterface
{
    /**
     * Returns the value of the given variable as managed by the current instance.
     *
     * @param string $name The name of the variable
     *
     * @return mixed|null The value of the given variable or null when it is not found
     */
    public function getEnv($name);
}
