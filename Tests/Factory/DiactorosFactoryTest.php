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

use Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Antonio J. García Lagar <aj@garcialagar.es>
 *
 * @group legacy
 */
class DiactorosFactoryTest extends AbstractHttpMessageFactoryTest
{
    protected function buildHttpMessageFactory(): HttpMessageFactoryInterface
    {
        if (!class_exists('Zend\Diactoros\ServerRequestFactory')) {
            $this->markTestSkipped('Zend Diactoros is not installed.');
        }

        return new DiactorosFactory();
    }
}
