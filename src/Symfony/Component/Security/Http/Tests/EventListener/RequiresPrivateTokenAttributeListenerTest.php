<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerAttributeEvent;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Attribute\RequiresPrivateToken;
use Symfony\Component\Security\Http\EventListener\RequiresPrivateTokenAttributeListener;
use Symfony\Component\Security\Http\PrivacyPass\PrivateTokenManagerInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Contracts\Service\ServiceProviderInterface;

class RequiresPrivateTokenAttributeListenerTest extends TestCase
{
    public function testItLetsARequestCarryingAValidTokenThrough()
    {
        $listener = new RequiresPrivateTokenAttributeListener($this->managers($this->manager(true)));

        $listener->onKernelControllerAttribute($this->event(new RequiresPrivateToken()));

        $this->addToAssertionCount(1);
    }

    public function testItChallengesARequestCarryingNoValidToken()
    {
        $listener = new RequiresPrivateTokenAttributeListener($this->managers($this->manager(false)));

        try {
            $listener->onKernelControllerAttribute($this->event(new RequiresPrivateToken()));
            $this->fail(\sprintf('An "%s" should have been thrown.', UnauthorizedHttpException::class));
        } catch (UnauthorizedHttpException $e) {
            $this->assertSame(401, $e->getStatusCode());
            $this->assertSame('PrivateToken challenge="abc"', $e->getHeaders()['WWW-Authenticate']);
            $this->assertSame('A Privacy Pass token is required.', $e->getMessage());
        }
    }

    public function testItSkipsTheMethodsTheTokenIsNotRequiredFor()
    {
        $manager = $this->createMock(PrivateTokenManagerInterface::class);
        $manager->expects($this->never())->method('isTokenValid');
        $listener = new RequiresPrivateTokenAttributeListener($this->managers($manager));

        $listener->onKernelControllerAttribute($this->event(new RequiresPrivateToken(methods: 'POST'), 'GET'));

        $this->addToAssertionCount(1);
    }

    public function testItRequiresATokenForTheMethodsItIsAskedFor()
    {
        $listener = new RequiresPrivateTokenAttributeListener($this->managers($this->manager(false)));

        $this->expectException(UnauthorizedHttpException::class);

        $listener->onKernelControllerAttribute($this->event(new RequiresPrivateToken(methods: ['POST'])));
    }

    public function testItReportsAConfigurationThatDoesNotExist()
    {
        $managers = $this->createStub(ServiceProviderInterface::class);
        $managers->method('has')->willReturn(false);
        $managers->method('getProvidedServices')->willReturn(['contact' => PrivateTokenManagerInterface::class]);
        $listener = new RequiresPrivateTokenAttributeListener($managers);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Privacy Pass configuration "default" does not exist. Did you forget to configure it? Available configurations: "contact".');

        $listener->onKernelControllerAttribute($this->event(new RequiresPrivateToken()));
    }

    public function testItListensToTheAttributeItHandlesAndToTheResponse()
    {
        $this->assertSame(
            [
                KernelEvents::CONTROLLER.'.'.RequiresPrivateToken::class => 'onKernelControllerAttribute',
                KernelEvents::RESPONSE => 'onKernelResponse',
            ],
            RequiresPrivateTokenAttributeListener::getSubscribedEvents(),
        );
    }

    public function testItReportsTheVerdictToTheController()
    {
        $listener = new RequiresPrivateTokenAttributeListener($this->managers($this->manager(true)));
        $event = $this->event(new RequiresPrivateToken());

        $listener->onKernelControllerAttribute($event);

        $this->assertTrue($event->kernelEvent->getRequest()->attributes->get(SecurityRequestAttributes::PRIVATE_TOKEN_VALID));
    }

    public function testAnOptionalTokenLetsTheControllerRun()
    {
        $listener = new RequiresPrivateTokenAttributeListener($this->managers($this->manager(false)));
        $event = $this->event(new RequiresPrivateToken(optional: true));

        $listener->onKernelControllerAttribute($event);

        $this->assertFalse($event->kernelEvent->getRequest()->attributes->get(SecurityRequestAttributes::PRIVATE_TOKEN_VALID));
    }

    public function testTheResponseOfAnOptionalTokenCarriesTheChallenge()
    {
        $listener = new RequiresPrivateTokenAttributeListener($this->managers($this->manager(false)));
        $event = $this->event(new RequiresPrivateToken(optional: true));
        $listener->onKernelControllerAttribute($event);

        $response = new Response('<captcha>');
        $listener->onKernelResponse($this->responseEvent($event->kernelEvent->getRequest(), $response));

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('PrivateToken challenge="abc"', $response->headers->get('WWW-Authenticate'));
        $this->assertSame('<captcha>', $response->getContent());
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
    }

    public function testTheResponseOfAValidOptionalTokenIsLeftAlone()
    {
        $listener = new RequiresPrivateTokenAttributeListener($this->managers($this->manager(true)));
        $event = $this->event(new RequiresPrivateToken(optional: true));
        $listener->onKernelControllerAttribute($event);

        $response = new Response('<page>');
        $listener->onKernelResponse($this->responseEvent($event->kernelEvent->getRequest(), $response));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($response->headers->has('WWW-Authenticate'));
    }

    public function testAResponseThatIsNotASuccessIsLeftAlone()
    {
        $listener = new RequiresPrivateTokenAttributeListener($this->managers($this->manager(false)));
        $event = $this->event(new RequiresPrivateToken(optional: true));
        $listener->onKernelControllerAttribute($event);

        $response = new Response('<moved>', 302);
        $listener->onKernelResponse($this->responseEvent($event->kernelEvent->getRequest(), $response));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertFalse($response->headers->has('WWW-Authenticate'));
    }

    public function testASubRequestIsLeftAlone()
    {
        $listener = new RequiresPrivateTokenAttributeListener($this->managers($this->manager(false)));
        $event = $this->event(new RequiresPrivateToken(optional: true));
        $listener->onKernelControllerAttribute($event);

        $response = new Response('<fragment>');
        $listener->onKernelResponse($this->responseEvent($event->kernelEvent->getRequest(), $response, HttpKernelInterface::SUB_REQUEST));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testAResponseToARequestThatWasNotChallengedIsLeftAlone()
    {
        $listener = new RequiresPrivateTokenAttributeListener($this->managers($this->manager(false)));

        $response = new Response('<page>');
        $listener->onKernelResponse($this->responseEvent(Request::create('https://origin.example/contact'), $response));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testTheChallengeIsCarriedOnce()
    {
        $listener = new RequiresPrivateTokenAttributeListener($this->managers($this->manager(false)));
        $event = $this->event(new RequiresPrivateToken(optional: true));
        $listener->onKernelControllerAttribute($event);
        $request = $event->kernelEvent->getRequest();

        $listener->onKernelResponse($this->responseEvent($request, new Response('<captcha>')));
        $second = new Response('<page>');
        $listener->onKernelResponse($this->responseEvent($request, $second));

        $this->assertSame(200, $second->getStatusCode());
    }

    private function responseEvent(Request $request, Response $response, int $requestType = HttpKernelInterface::MAIN_REQUEST): ResponseEvent
    {
        return new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, $requestType, $response);
    }

    private function manager(bool $valid): PrivateTokenManagerInterface
    {
        $manager = $this->createStub(PrivateTokenManagerInterface::class);
        $manager->method('isTokenValid')->willReturn($valid);
        $manager->method('getChallengeHeader')->willReturn('PrivateToken challenge="abc"');

        return $manager;
    }

    private function managers(PrivateTokenManagerInterface $manager): ServiceProviderInterface
    {
        $managers = $this->createStub(ServiceProviderInterface::class);
        $managers->method('has')->willReturn(true);
        $managers->method('get')->willReturn($manager);

        return $managers;
    }

    private function event(RequiresPrivateToken $attribute, string $method = 'POST'): ControllerAttributeEvent
    {
        $kernelEvent = new ControllerEvent(
            $this->createStub(HttpKernelInterface::class),
            static fn () => null,
            Request::create('https://origin.example/contact', $method),
            HttpKernelInterface::MAIN_REQUEST,
        );

        return new ControllerAttributeEvent($attribute, $kernelEvent);
    }
}
