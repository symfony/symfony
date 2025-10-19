<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Attribute\RequestRateLimit;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\RateLimiter\RequestRateLimiter;

/**
 * Handles the RequestRateLimit attribute.
 *
 * @author Santiago San Martin <sanmartindev@gmail.com>
 *
 * @internal
 */
final class RequestRateLimiterListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly RequestRateLimiter $requestRateLimiter,
    ) {
    }

    public function onKernelControllerArguments(ControllerArgumentsEvent $event): void
    {
        if (!$attributes = $event->getAttributes(RequestRateLimit::class)) {
            return;
        }

        $request = $event->getRequest();
        foreach ($attributes as $attribute) {
            if ($attribute->methods && !\in_array($request->getMethod(), array_map('strtoupper', $attribute->methods), true)) {
                continue;
            }

            $rateLimit = $this->requestRateLimiter->addRateLimiterFactories($attribute->rateLimitersFactoriesIds)->consume($request);
            if (false === $rateLimit->isAccepted()) {
                $delta = $rateLimit->getRetryAfter()->format('U.u') - microtime(true);
                throw new TooManyRequestsHttpException((int) $delta);
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER_ARGUMENTS => ['onKernelControllerArguments', 40]];
    }
}
