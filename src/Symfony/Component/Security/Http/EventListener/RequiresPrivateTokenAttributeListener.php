<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerAttributeEvent;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Attribute\RequiresPrivateToken;
use Symfony\Component\Security\Http\PrivacyPass\PrivateTokenManagerInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * Handles the RequiresPrivateToken attribute on controllers.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
final class RequiresPrivateTokenAttributeListener implements EventSubscriberInterface
{
    /**
     * @var \WeakMap<Request, PrivateTokenManagerInterface>
     */
    private \WeakMap $challenged;

    /**
     * @param ServiceProviderInterface<PrivateTokenManagerInterface> $managers
     */
    public function __construct(
        private readonly ServiceProviderInterface $managers,
    ) {
        $this->challenged = new \WeakMap();
    }

    /**
     * @param ControllerAttributeEvent<RequiresPrivateToken, ControllerEvent> $event
     */
    public function onKernelControllerAttribute(ControllerAttributeEvent $event): void
    {
        $request = $event->kernelEvent->getRequest();
        $attribute = $event->attribute;

        if ($attribute->methods && !\in_array($request->getMethod(), $attribute->methods, true)) {
            return;
        }

        if (!$this->managers->has($attribute->name)) {
            throw new \InvalidArgumentException(\sprintf('Privacy Pass configuration "%s" does not exist. Did you forget to configure it? Available configurations: "%s".', $attribute->name, implode('", "', array_keys($this->managers->getProvidedServices()))));
        }

        $manager = $this->managers->get($attribute->name);
        $valid = $manager->isTokenValid($request);

        $request->attributes->set(SecurityRequestAttributes::PRIVATE_TOKEN_VALID, $valid);

        if ($valid) {
            return;
        }

        if (!$attribute->optional) {
            throw new UnauthorizedHttpException($manager->getChallengeHeader($request), 'A Privacy Pass token is required.');
        }

        $this->challenged[$request] = $manager;
    }

    /**
     * Turns the response of a controller that ran without an acceptable token into a challenge, keeping the body it answered with: a client that cannot redeem a token gets the fallback page, and one that can redeems a token on the next request.
     *
     * A response that already reports something else than a success is left alone.
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || !($manager = $this->challenged[$request] ?? null)) {
            return;
        }

        unset($this->challenged[$request]);
        $response = $event->getResponse();

        if (!$response->isSuccessful()) {
            return;
        }

        $response->setStatusCode(Response::HTTP_UNAUTHORIZED);
        $response->headers->set('WWW-Authenticate', $manager->getChallengeHeader($request));
        // the challenge is bound to a context of this client, and the body it travels with is the
        // one answered to a client holding no token
        $response->setPrivate();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER.'.'.RequiresPrivateToken::class => 'onKernelControllerAttribute',
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }
}
