<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Controller\ArgumentResolver;

use PHPUnit\Framework\TestCase;
use Symfony\Component\BrowserKit\Request as RandomRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestValueResolver;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NearMissValueResolverException;

class RequestValueResolverTest extends TestCase
{
    public function testSameRequestReturned()
    {
        $resolver = new RequestValueResolver();
        $expectedRequest = Request::create('/');
        $actualRequest = $resolver->resolve($expectedRequest, new ArgumentMetadata('request', Request::class, false, false, null));
        self::assertCount(1, $actualRequest);
        self::assertSame($expectedRequest, $actualRequest[0] ?? null);
    }

    public function testRequestIsNotResolvedForRandomClass()
    {
        $resolver = new RequestValueResolver();
        $expectedRequest = Request::create('/');
        $actualRequest = $resolver->resolve($expectedRequest, new ArgumentMetadata('request', self::class, false, false, null));
        self::assertCount(0, $actualRequest);
    }

    public function testExceptionThrownForRandomRequestClass()
    {
        $resolver = new RequestValueResolver();
        $expectedRequest = Request::create('/');
        $this->expectException(NearMissValueResolverException::class);
        $resolver->resolve($expectedRequest, new ArgumentMetadata('request', RandomRequest::class, false, false, null));
    }
}
