<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Controller\ArgumentResolver;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\EventListener\ControllerAttributesListener;
use Symfony\Component\HttpKernel\EventListener\RateLimitAttributeListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\RateLimiter\AppliedRateLimit;
use Symfony\Component\RateLimiter\RateLimit;

/**
 * Resolves the RateLimit arguments of a controller to the rate limit consumed by its #[RateLimit] attributes.
 *
 * When several attributes apply, the argument gets the rate limit with the fewest remaining calls.
 * When none applies, the argument gets its default value, or null when it is nullable.
 *
 * @author Ayyoub AFW-ALLAH <ayyoub.afwallah@gmail.com>
 */
final class RateLimitValueResolver implements ValueResolverInterface, EventSubscriberInterface
{
    /** @var \WeakMap<Request, list<ArgumentMetadata>> */
    private \WeakMap $rateLimitArguments;

    public function __construct()
    {
        $this->rateLimitArguments = new \WeakMap();
    }

    public function resolve(Request $request, ArgumentMetadata $argument): array
    {
        if (RateLimit::class !== $argument->getType() || $argument->isVariadic()) {
            return [];
        }

        $this->rateLimitArguments[$request] = [...$this->rateLimitArguments[$request] ?? [], $argument];

        return [$argument];
    }

    public function onKernelControllerArguments(ControllerArgumentsEvent $event): void
    {
        $request = $event->getRequest();

        if (!$rateLimitArguments = $this->rateLimitArguments[$request] ?? []) {
            return;
        }

        unset($this->rateLimitArguments[$request]);

        $arguments = $event->getArguments();
        foreach ($arguments as $i => $argument) {
            if (\in_array($argument, $rateLimitArguments, true)) {
                $arguments[$i] = $this->getRateLimit($request, $argument);
            }
        }
        $event->setArguments($arguments);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER_ARGUMENTS => ['method' => 'onKernelControllerArguments', 'priority' => -10100, 'after' => ControllerAttributesListener::class],
        ];
    }

    private function getRateLimit(Request $request, ArgumentMetadata $argument): ?RateLimit
    {
        $selected = null;
        $applied = $request->attributes->get(RateLimitAttributeListener::RATE_LIMIT_ATTRIBUTE);

        foreach (\is_array($applied) ? $applied : [] as $candidate) {
            if ($candidate instanceof AppliedRateLimit && (!$selected || $candidate->getRemainingCalls() < $selected->getRemainingCalls())) {
                $selected = $candidate;
            }
        }

        return $selected->rateLimit ?? match (true) {
            $argument->hasDefaultValue() => $argument->getDefaultValue(),
            $argument->isNullable() => null,
            default => throw new \LogicException(\sprintf('Cannot resolve the "$%s" argument of "%s()": no "#[RateLimit]" attribute applies to the request, either because the controller has none or because their "$methods" or "$if" options exclude the request. Make the argument nullable if this is expected.', $argument->getName(), $argument->getControllerName())),
        };
    }
}
