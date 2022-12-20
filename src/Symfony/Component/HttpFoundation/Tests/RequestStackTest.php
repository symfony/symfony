<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class RequestStackTest extends TestCase
{
    use ExpectDeprecationTrait;

    public function testGetCurrentRequest()
    {
        $requestStack = new RequestStack();
        self::assertNull($requestStack->getCurrentRequest());

        $request = Request::create('/foo');

        $requestStack->push($request);
        self::assertSame($request, $requestStack->getCurrentRequest());

        self::assertSame($request, $requestStack->pop());
        self::assertNull($requestStack->getCurrentRequest());

        self::assertNull($requestStack->pop());
    }

    public function testGetMainRequest()
    {
        $requestStack = new RequestStack();
        self::assertNull($requestStack->getMainRequest());

        $mainRequest = Request::create('/foo');
        $subRequest = Request::create('/bar');

        $requestStack->push($mainRequest);
        $requestStack->push($subRequest);

        self::assertSame($mainRequest, $requestStack->getMainRequest());
    }

    /**
     * @group legacy
     */
    public function testGetMasterRequest()
    {
        $requestStack = new RequestStack();
        self::assertNull($requestStack->getMasterRequest());

        $masterRequest = Request::create('/foo');
        $subRequest = Request::create('/bar');

        $requestStack->push($masterRequest);
        $requestStack->push($subRequest);

        $this->expectDeprecation('Since symfony/http-foundation 5.3: "Symfony\Component\HttpFoundation\RequestStack::getMasterRequest()" is deprecated, use "getMainRequest()" instead.');
        self::assertSame($masterRequest, $requestStack->getMasterRequest());
    }

    public function testGetParentRequest()
    {
        $requestStack = new RequestStack();
        self::assertNull($requestStack->getParentRequest());

        $mainRequest = Request::create('/foo');

        $requestStack->push($mainRequest);
        self::assertNull($requestStack->getParentRequest());

        $firstSubRequest = Request::create('/bar');

        $requestStack->push($firstSubRequest);
        self::assertSame($mainRequest, $requestStack->getParentRequest());

        $secondSubRequest = Request::create('/baz');

        $requestStack->push($secondSubRequest);
        self::assertSame($firstSubRequest, $requestStack->getParentRequest());
    }
}
