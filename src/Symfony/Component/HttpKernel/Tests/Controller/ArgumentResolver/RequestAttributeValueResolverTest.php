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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestAttributeValueResolver;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RequestAttributeValueResolverTest extends TestCase
{
    public function testValidIntWithinRangeWorks()
    {
        $resolver = new RequestAttributeValueResolver();
        $request = new Request();
        $request->attributes->set('id', '123');
        $metadata = new ArgumentMetadata('id', 'int', false, false, null);

        $result = iterator_to_array($resolver->resolve($request, $metadata));

        $this->assertSame([123], $result);
    }

    public function testInvalidStringBecomes404()
    {
        $resolver = new RequestAttributeValueResolver();
        $request = new Request();
        $request->attributes->set('id', 'abc');
        $metadata = new ArgumentMetadata('id', 'int', false, false, null);

        $this->expectException(NotFoundHttpException::class);
        iterator_to_array($resolver->resolve($request, $metadata));
    }

    public function testOutOfRangeIntBecomes404()
    {
        $resolver = new RequestAttributeValueResolver();
        $request = new Request();
        // one more than PHP_INT_MAX on 64-bit (string input)
        $request->attributes->set('id', '9223372036854775808');
        $metadata = new ArgumentMetadata('id', 'int', false, false, null);

        $this->expectException(NotFoundHttpException::class);
        iterator_to_array($resolver->resolve($request, $metadata));
    }

    public function testNullableIntAllowsNull()
    {
        $resolver = new RequestAttributeValueResolver();
        $request = new Request();
        $request->attributes->set('id', null);
        $metadata = new ArgumentMetadata('id', 'int', false, true, null);

        $result = iterator_to_array($resolver->resolve($request, $metadata));

        $this->assertSame([null], $result);
    }
}
