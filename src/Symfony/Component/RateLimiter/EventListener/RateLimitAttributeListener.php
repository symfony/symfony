<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\Attribute\RateLimit;

/**
 * Rate limit attribute listener for controller
 *
 * @see https://symfony.com/doc/current/rate_limiter.html
 *
 * @author Raziel Rodrigues <raziel.rodrigues@outlook.pt>
 */
class RateLimitAttributeListener implements EventSubscriberInterface
{
    public function __construct(
        private ?ExpressionLanguage $expressionLanguage = null,
    ) {}

    public function onKernelControllerArguments(ControllerArgumentsEvent $event): void
    {
        /** @var RateLimit[] $attributes */
        if (!\is_array($attributes = $event->getAttributes()[RateLimit::class] ?? null)) {
            return;
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER_ARGUMENTS => ['onKernelControllerArguments', 20]];
    }
}
