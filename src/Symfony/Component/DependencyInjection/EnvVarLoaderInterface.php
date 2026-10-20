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
 * Provides more env vars, as key/value pairs, to be read with the %env()% syntax.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface EnvVarLoaderInterface
{
    /**
     * @return array<string|\Stringable> Key/value pairs that can be accessed using the regular "%env()%" syntax
     */
    public function loadEnvVars(): array;
}
