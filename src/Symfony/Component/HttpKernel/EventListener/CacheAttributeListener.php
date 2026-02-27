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
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ControllerAttributeEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Handles HTTP cache headers configured via the Cache attribute.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class CacheAttributeListener implements EventSubscriberInterface
{
    public function __construct(
        private ?ExpressionLanguage $expressionLanguage = null,
    ) {
    }

    public function onKernelControllerAttribute(ControllerAttributeEvent $event): void
    {
        $cache = $event->attribute;
        $kernelEvent = $event->kernelEvent;
        $request = $event->kernelEvent->getRequest();

        if ($kernelEvent instanceof ControllerArgumentsEvent) {
            if (null !== $variables = $this->getVariables($cache, $request, $kernelEvent)) {
                $cache->variables = $variables;
            }
            $this->processAttributeBeforeController($cache, $request, $kernelEvent);

            return;
        }

        if ($kernelEvent instanceof ResponseEvent) {
            $response = $kernelEvent->getResponse();

            // http://tools.ietf.org/html/draft-ietf-httpbis-p4-conditional-12#section-3.1
            if (!\in_array($response->getStatusCode(), [200, 203, 300, 301, 302, 304, 404, 410], true)) {
                return;
            }

            $this->processAttributeAfterController($cache, $request, $response);

            return;
        }
    }

    /**
     * @internal since Symfony 8.1, use onKernelControllerAttribute() instead
     */
    public function onKernelControllerArguments(ControllerArgumentsEvent $event): void
    {
        $request = $event->getRequest();

        /** @var Cache[] $attributes */
        if (!$attributes = $request->attributes->get('_cache') ?? $event->getAttributes(Cache::class)) {
            return;
        }

        $request->attributes->set('_cache', $attributes);
        $variables = null;

        foreach ($attributes as $cache) {
            if (null !== $variables ??= $this->getVariables($cache, $request, $event)) {
                $cache->variables = $variables;
            }
            $this->processAttributeBeforeController($cache, $request, $event);
        }
    }

    /**
     * @internal since Symfony 8.1, use onKernelControllerAttribute() instead
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();

        /** @var Cache[] $attributes */
        if (!\is_array($attributes = $request->attributes->get('_cache'))) {
            return;
        }
        $response = $event->getResponse();

        // http://tools.ietf.org/html/draft-ietf-httpbis-p4-conditional-12#section-3.1
        if (!\in_array($response->getStatusCode(), [200, 203, 300, 301, 302, 304, 404, 410], true)) {
            return;
        }

        $hasVary = null;
        $hasCacheControlDirective = null;

        for ($i = \count($attributes) - 1; 0 <= $i; --$i) {
            $this->processAttributeAfterController($attributes[$i], $request, $response, $hasVary, $hasCacheControlDirective);
        }
    }

    public static function getSubscribedEvents(): array
    {
        if (!class_exists(ControllerAttributesListener::class, false)) {
            return [
                KernelEvents::CONTROLLER_ARGUMENTS => ['onKernelControllerArguments', 10],
                KernelEvents::RESPONSE => ['onKernelResponse', -10],
            ];
        }

        return [
            KernelEvents::CONTROLLER_ARGUMENTS.'.'.Cache::class => 'onKernelControllerAttribute',
            KernelEvents::RESPONSE.'.'.Cache::class => 'onKernelControllerAttribute',
        ];
    }

    public function reset(): void
    {
    }

    private function processAttributeBeforeController(Cache $cache, Request $request, ControllerArgumentsEvent $event): void
    {
        if (!\is_bool($cache->if)) {
            if (!\is_bool($if = $this->evaluate($cache->if, $cache->variables))) {
                throw new \TypeError(\sprintf('The value of the "$if" option of the "%s" attribute must evaluate to a boolean, "%s" given.', Cache::class, get_debug_type($if)));
            }

            $cache->if = $if;
        }

        if (!$cache->if) {
            return;
        }

        $response = null;

        if (null !== $cache->lastModified && !$cache->lastModified instanceof \DateTimeInterface) {
            $lastModified = $this->evaluate($cache->lastModified, $cache->variables);
            ($response ??= new Response())->setLastModified($lastModified);
            $cache->lastModified = $lastModified;
        }

        if (null !== $cache->etag) {
            $etag = hash('sha256', $this->evaluate($cache->etag, $cache->variables));
            ($response ??= new Response())->setEtag($etag);
            $cache->etag = $etag;
        }

        if ($response?->isNotModified($request)) {
            $event->setController(static fn () => $response);
            $event->stopPropagation();
        }
    }

    private function processAttributeAfterController(Cache $cache, Request $request, Response $response, ?bool &$hasVary = null, ?callable &$hasCacheControlDirective = null): void
    {
        if (!$cache->if) {
            return;
        }

        // Check if the response has a Vary header that should be considered, ignoring cases where
        // it's only 'Accept-Language' and the request has the '_vary_by_language' attribute
        $hasVary ??= ['Accept-Language'] === $response->getVary() ? !$request->attributes->get('_vary_by_language') : $response->hasVary();
        // Check if cache-control directive was set manually in cacheControl (not auto computed)
        $hasCacheControlDirective ??= new class($response->headers) extends HeaderBag {
            public function __construct(private parent $headerBag)
            {
            }

            public function __invoke(string $key): bool
            {
                return \array_key_exists($key, $this->headerBag->cacheControl);
            }
        };

        if (null !== $cache->lastModified && !$response->headers->has('Last-Modified')) {
            $response->setLastModified($cache->lastModified);
        }

        if (null !== $cache->etag && !$response->headers->has('ETag')) {
            $response->setEtag($cache->etag);
        }

        if (null !== $cache->smaxage && !$hasCacheControlDirective('s-maxage')) {
            $response->setSharedMaxAge($this->toSeconds($cache->smaxage));
        }

        if ($cache->mustRevalidate) {
            $response->headers->addCacheControlDirective('must-revalidate');
        }

        if (null !== $cache->maxage && !$hasCacheControlDirective('max-age')) {
            $response->setMaxAge($this->toSeconds($cache->maxage));
        }

        if (null !== $cache->maxStale && !$hasCacheControlDirective('max-stale')) {
            $response->headers->addCacheControlDirective('max-stale', $this->toSeconds($cache->maxStale));
        }

        if (null !== $cache->staleWhileRevalidate && !$hasCacheControlDirective('stale-while-revalidate')) {
            $response->headers->addCacheControlDirective('stale-while-revalidate', $this->toSeconds($cache->staleWhileRevalidate));
        }

        if (null !== $cache->staleIfError && !$hasCacheControlDirective('stale-if-error')) {
            $response->headers->addCacheControlDirective('stale-if-error', $this->toSeconds($cache->staleIfError));
        }

        if (null !== $cache->expires && !$response->headers->has('Expires')) {
            $response->setExpires(new \DateTimeImmutable('@'.strtotime($cache->expires, time())));
        }

        if (!$hasVary && $cache->vary) {
            $response->setVary($cache->vary, false);
        }

        $hasPublicOrPrivateCacheControlDirective = \is_bool($cache->public) && ($hasCacheControlDirective('public') || $hasCacheControlDirective('private'));

        if (true === $cache->public && !$hasPublicOrPrivateCacheControlDirective) {
            $response->setPublic();
        }

        if (false === $cache->public && !$hasPublicOrPrivateCacheControlDirective) {
            $response->setPrivate();
        }

        if (true === $cache->noStore) {
            $response->headers->addCacheControlDirective('no-store');
        }

        if (false === $cache->noStore) {
            $response->headers->removeCacheControlDirective('no-store');
        }
    }

    private function getVariables(Cache $cache, Request $request, ControllerArgumentsEvent $event): ?array
    {
        if (\is_bool($cache->if) && null === $cache->lastModified && null === $cache->etag) {
            return null;
        }

        $controller = $event->getController();
        $controller = match (true) {
            \is_object($controller) && !$controller instanceof \Closure => $controller,
            \is_array($controller) && \is_object($controller[0]) => $controller[0],
            default => null,
        };

        return array_merge([
            'request' => $request,
            'args' => $arguments = $event->getNamedArguments(),
            'this' => $controller,
        ], $request->attributes->all(), $arguments);
    }

    private function evaluate(string|Expression|\Closure $closureOrExpression, array $variables): mixed
    {
        if ($closureOrExpression instanceof \Closure) {
            return $closureOrExpression($variables['args'], $variables['request'], $variables['this']);
        }

        return $this->getExpressionLanguage()->evaluate($closureOrExpression, $variables);
    }

    private function getExpressionLanguage(): ExpressionLanguage
    {
        return $this->expressionLanguage ??= class_exists(ExpressionLanguage::class)
            ? new ExpressionLanguage()
            : throw new \LogicException('Unable to use expressions as the Symfony ExpressionLanguage component is not installed. Try running "composer require symfony/expression-language".');
    }

    private function toSeconds(int|string $time): int
    {
        if (!is_numeric($time)) {
            $now = time();
            $time = strtotime($time, $now) - $now;
        }

        return $time;
    }
}
