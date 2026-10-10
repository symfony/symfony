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
use Symfony\Component\HttpKernel\Attribute\MapRateLimit;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\EventListener\ControllerAttributesListener;
use Symfony\Component\HttpKernel\EventListener\RateLimitAttributeListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\RateLimiter\AppliedRateLimit;
use Symfony\Component\RateLimiter\RateLimit;

/**
 * Resolves the RateLimit controller arguments, filtered by #[MapRateLimit] when present.
 *
 * @author Ayyoub AFW-ALLAH <ayyoub.afwallah@gmail.com>
 */
final class MapRateLimitValueResolver implements ValueResolverInterface, EventSubscriberInterface
{
    /** @var \WeakMap<Request, list<ArgumentMetadata>> */
    private \WeakMap $mappedArguments;

    public function __construct()
    {
        $this->mappedArguments = new \WeakMap();
    }

    public function resolve(Request $request, ArgumentMetadata $argument): array
    {
        $mapped = (bool) $argument->getAttributesOfType(MapRateLimit::class, ArgumentMetadata::IS_INSTANCEOF);

        if (!$mapped && (RateLimit::class !== $argument->getType() || $argument->isVariadic())) {
            return [];
        }

        if ($mapped && RateLimit::class !== $argument->getType()) {
            throw new \LogicException(\sprintf('The "$%s" argument of "%s" must be typed as "%s".', $argument->getName(), $argument->getControllerName(), RateLimit::class));
        }

        if ($mapped && $argument->isVariadic()) {
            throw new \LogicException(\sprintf('The "$%s" argument of "%s" must not be variadic.', $argument->getName(), $argument->getControllerName()));
        }

        $this->mappedArguments[$request] = [...$this->mappedArguments[$request] ?? [], $argument];

        return [$argument];
    }

    public function onKernelControllerArguments(ControllerArgumentsEvent $event): void
    {
        $request = $event->getRequest();

        if (!$mappedArguments = $this->mappedArguments[$request] ?? []) {
            return;
        }

        unset($this->mappedArguments[$request]);

        $arguments = $event->getArguments();
        foreach ($arguments as $i => $argument) {
            if (\in_array($argument, $mappedArguments, true)) {
                $arguments[$i] = $this->getRateLimit($argument, $request);
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

    private function getRateLimit(ArgumentMetadata $argument, Request $request): ?RateLimit
    {
        $mapping = $argument->getAttributesOfType(MapRateLimit::class, ArgumentMetadata::IS_INSTANCEOF)[0] ?? new MapRateLimit();
        $applied = $request->attributes->get(RateLimitAttributeListener::RATE_LIMIT_ATTRIBUTE, []);
        $selected = $this->selectRateLimit($mapping, \is_array($applied) ? $applied : []);

        return $selected?->rateLimit ?? match (true) {
            $argument->hasDefaultValue() => $argument->getDefaultValue(),
            $argument->isNullable() => null,
            default => throw new \RuntimeException(\sprintf('Could not resolve the "$%s" argument of "%s": no matching rate limit was applied to the request.', $argument->getName(), $argument->getControllerName())),
        };
    }

    private function selectRateLimit(MapRateLimit $mapping, array $appliedRateLimits): ?AppliedRateLimit
    {
        $selected = null;

        foreach ($appliedRateLimits as $candidate) {
            if (!$candidate instanceof AppliedRateLimit) {
                continue;
            }

            if (null !== $mapping->exposed && $mapping->exposed !== $candidate->exposeHeaders) {
                continue;
            }

            if (null !== $mapping->limiter && $mapping->limiter !== $candidate->limiter) {
                continue;
            }

            if (!$selected || $candidate->getRemainingCalls() < $selected->getRemainingCalls()) {
                $selected = $candidate;
            }
        }

        return $selected;
    }
}
