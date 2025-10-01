<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Exception\UnsignedUriException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\EventListener\IsSignatureValidAttributeListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Tests\Fixtures\IsSignatureValidAttributeController;
use Symfony\Component\HttpKernel\Tests\Fixtures\IsSignatureValidAttributeMethodsController;

class IsSignatureValidAttributeListenerTest extends TestCase
{
    public function testInvokableControllerWithValidSignature()
    {
        $request = new Request();

        $signer = $this->createMock(UriSigner::class);
        $signer->expects($this->once())->method('verify')->with($request);
        $kernel = $this->createMock(HttpKernelInterface::class);

        $event = new ControllerArgumentsEvent(
            $kernel,
            new IsSignatureValidAttributeController(),
            [],
            $request,
            null
        );

        $listener = new IsSignatureValidAttributeListener($signer);
        $listener->onKernelControllerArguments($event);
    }

    public function testNoAttributeSkipsValidation()
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $signer = $this->createMock(UriSigner::class);
        $signer->expects($this->never())->method('verify');

        $event = new ControllerArgumentsEvent(
            $kernel,
            [new IsSignatureValidAttributeMethodsController(), 'noAttribute'],
            [],
            new Request(),
            null
        );

        $listener = new IsSignatureValidAttributeListener($signer);
        $listener->onKernelControllerArguments($event);
    }

    public function testDefaultCheckRequestSucceeds()
    {
        $request = new Request();
        $signer = $this->createMock(UriSigner::class);
        $signer->expects($this->once())->method('verify')->with($request);
        $kernel = $this->createMock(HttpKernelInterface::class);

        $event = new ControllerArgumentsEvent(
            $kernel,
            [new IsSignatureValidAttributeMethodsController(), 'withDefaultBehavior'],
            [],
            $request,
            null
        );

        $listener = new IsSignatureValidAttributeListener($signer);
        $listener->onKernelControllerArguments($event);
    }

    public function testCheckRequestFailsThrowsHttpException()
    {
        $request = new Request();
        $signer = $this->createMock(UriSigner::class);
        $signer->expects($this->once())->method('verify')->willThrowException(new UnsignedUriException());
        $kernel = $this->createMock(HttpKernelInterface::class);

        $event = new ControllerArgumentsEvent(
            $kernel,
            [new IsSignatureValidAttributeMethodsController(), 'withDefaultBehavior'],
            [],
            $request,
            null
        );

        $listener = new IsSignatureValidAttributeListener($signer);

        $this->expectException(UnsignedUriException::class);
        $listener->onKernelControllerArguments($event);
    }

    public function testMultipleAttributesAllValid()
    {
        $request = new Request();

        $signer = $this->createMock(UriSigner::class);
        $signer->expects($this->exactly(2))->method('verify')->with($request);
        $kernel = $this->createMock(HttpKernelInterface::class);

        $event = new ControllerArgumentsEvent(
            $kernel,
            [new IsSignatureValidAttributeMethodsController(), 'withMultiple'],
            [],
            $request,
            null
        );

        $listener = new IsSignatureValidAttributeListener($signer);
        $listener->onKernelControllerArguments($event);
    }

    public function testValidationWithStringMethod()
    {
        $request = new Request([], [], [], [], [], ['REQUEST_METHOD' => 'POST']);

        $signer = $this->createMock(UriSigner::class);
        $signer->expects($this->once())->method('verify')->with($request);
        $kernel = $this->createMock(HttpKernelInterface::class);

        $event = new ControllerArgumentsEvent(
            $kernel,
            [new IsSignatureValidAttributeMethodsController(), 'withPostOnly'],
            [],
            $request,
            null
        );

        $listener = new IsSignatureValidAttributeListener($signer);
        $listener->onKernelControllerArguments($event);
    }

    public function testValidationWithArrayMethods()
    {
        $request = new Request([], [], [], [], [], ['REQUEST_METHOD' => 'POST']);

        $signer = $this->createMock(UriSigner::class);
        $signer->expects($this->once())->method('verify')->with($request);
        $kernel = $this->createMock(HttpKernelInterface::class);

        $event = new ControllerArgumentsEvent(
            $kernel,
            [new IsSignatureValidAttributeMethodsController(), 'withGetAndPost'],
            [],
            $request,
            null
        );

        $listener = new IsSignatureValidAttributeListener($signer);
        $listener->onKernelControllerArguments($event);
    }

    public function testValidationSkippedForNonMatchingMethod()
    {
        $request = new Request([], [], [], [], [], ['REQUEST_METHOD' => 'GET']);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $signer = $this->createMock(UriSigner::class);
        $signer->expects($this->never())->method('verify');

        $event = new ControllerArgumentsEvent(
            $kernel,
            [new IsSignatureValidAttributeMethodsController(), 'withPostOnly'],
            [],
            $request,
            null
        );

        $listener = new IsSignatureValidAttributeListener($signer);
        $listener->onKernelControllerArguments($event);
    }
}
