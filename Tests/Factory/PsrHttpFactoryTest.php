<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PsrHttpMessage\Tests\Factory;

use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Antonio J. García Lagar <aj@garcialagar.es>
 */
class PsrHttpFactoryTest extends AbstractHttpMessageFactoryTest
{
    protected function buildHttpMessageFactory(): HttpMessageFactoryInterface
    {
        $factory = new Psr17Factory();

        return new PsrHttpFactory($factory, $factory, $factory, $factory);
    }
}
