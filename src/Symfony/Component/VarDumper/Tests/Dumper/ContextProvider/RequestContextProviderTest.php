<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Dumper\ContextProvider;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Dumper\ContextProvider\RequestContextProvider;

/**
 * @requires function \Symfony\Component\HttpFoundation\RequestStack::__construct
 */
class RequestContextProviderTest extends TestCase
{
    public function testGetContextOnNullRequest()
    {
        $requestStack = new RequestStack();
        $provider = new RequestContextProvider($requestStack);

        $this->assertNull($provider->getContext());
    }

    public function testGetContextOnRequest()
    {
        $request = Request::create('https://example.org/', 'POST');
        $request->attributes->set('_controller', 'MyControllerClass');

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $context = (new RequestContextProvider($requestStack))->getContext();
        $this->assertSame('https://example.org/', $context['uri']);
        $this->assertSame('POST', $context['method']);
        $this->assertInstanceOf(Data::class, $context['controller']);
        $this->assertSame('MyControllerClass', $context['controller']->getValue());
        $this->assertSame('https://example.org/', $context['uri']);
        $this->assertArrayHasKey('identifier', $context);
    }
}
