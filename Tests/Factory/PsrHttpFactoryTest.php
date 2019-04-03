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

use Http\Factory\Diactoros\ResponseFactory;
use Http\Factory\Diactoros\ServerRequestFactory;
use Http\Factory\Diactoros\StreamFactory;
use Http\Factory\Diactoros\UploadedFileFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Antonio J. García Lagar <aj@garcialagar.es>
 */
class PsrHttpFactoryTest extends AbstractHttpMessageFactoryTest
{
    protected function buildHttpMessageFactory()
    {
        if (class_exists('Nyholm\Psr7\Factory\Psr17Factory')) {
            $factory = new Psr17Factory();
            return new PsrHttpFactory($factory, $factory, $factory, $factory);
        }

        if (class_exists('Http\Factory\Diactoros\ServerRequestFactory')) {
            return new PsrHttpFactory(
                new ServerRequestFactory(),
                new StreamFactory(),
                new UploadedFileFactory(),
                new ResponseFactory()
            );
        }

        $this->markTestSkipped('No PSR-17 HTTP Factory installed.');
    }
}
