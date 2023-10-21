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

use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * The EnvVarProcessorInterface is implemented by objects that manage environment-like variables.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface EnvVarProcessorInterface
{
    /**
     * Returns the value of the given variable as managed by the current instance.
     *
     * @param string                  $prefix The namespace of the variable
     * @param string                  $prefix The namespace of the variable; when the empty string is passed, null values should be kept as is
     * @param string                  $name   The name of the variable within the namespace
     * @param \Closure(string): mixed $getEnv A closure that allows fetching more env vars
     *
     * @throws RuntimeException on error
     */
    public function getEnv(string $prefix, string $name, \Closure $getEnv): mixed;

    /**
     * @return array<string, string> The PHP-types managed by getEnv(), keyed by prefixes
     */
    public static function getProvidedTypes(): array;
}
