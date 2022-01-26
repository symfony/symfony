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

use Sensio\Bundle\FrameworkExtraBundle\Configuration\ConfigurationAnnotation;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * HttpCacheListener handles HTTP cache headers.
 *
 * It can be configured via the Cache annotation.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class CacheAttributeListener implements EventSubscriberInterface
{
    private $lastModifiedDates;
    private $etags;
    private $expressionLanguage;

    public function __construct()
    {
        $this->lastModifiedDates = new \SplObjectStorage();
        $this->etags = new \SplObjectStorage();
    }

    /**
     * Handles HTTP validation headers.
     */
    public function onKernelController(KernelEvent $event)
    {
        if (!$configuration = $this->getConfiguration($event)) {
            return;
        }

        if (!$configuration instanceof Cache) {
            return;
        }

        $request = $event->getRequest();
        $response = new Response();

        $lastModifiedDate = '';
        if ($configuration->getLastModified()) {
            $lastModifiedDate = $this->getExpressionLanguage()->evaluate($configuration->getLastModified(), $request->attributes->all());
            $response->setLastModified($lastModifiedDate);
        }

        $etag = '';
        if ($configuration->getEtag()) {
            $etag = hash('sha256', $this->getExpressionLanguage()->evaluate($configuration->getEtag(), $request->attributes->all()));
            $response->setEtag($etag);
        }

        if ($response->isNotModified($request)) {
            $event->setController(function () use ($response) {
                return $response;
            });
            $event->stopPropagation();
        } else {
            if ($etag) {
                $this->etags[$request] = $etag;
            }
            if ($lastModifiedDate) {
                $this->lastModifiedDates[$request] = $lastModifiedDate;
            }
        }
    }

    /**
     * Modifies the response to apply HTTP cache headers when needed.
     */
    public function onKernelResponse(KernelEvent $event)
    {
        if (!$configuration = $this->getConfiguration($event)) {
            return;
        }

        if (!$configuration instanceof Cache) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        // http://tools.ietf.org/html/draft-ietf-httpbis-p4-conditional-12#section-3.1
        if (!\in_array($response->getStatusCode(), [200, 203, 300, 301, 302, 304, 404, 410])) {
            return;
        }

        if (!$response->headers->hasCacheControlDirective('s-maxage') && null !== $age = $configuration->getSMaxAge()) {
            $age = $this->convertToSecondsIfNeeded($age);

            $response->setSharedMaxAge($age);
        }

        if ($configuration->mustRevalidate()) {
            $response->headers->addCacheControlDirective('must-revalidate');
        }

        if (!$response->headers->hasCacheControlDirective('max-age') && null !== $age = $configuration->getMaxAge()) {
            $age = $this->convertToSecondsIfNeeded($age);

            $response->setMaxAge($age);
        }

        if (!$response->headers->hasCacheControlDirective('max-stale') && null !== $stale = $configuration->getMaxStale()) {
            $stale = $this->convertToSecondsIfNeeded($stale);

            $response->headers->addCacheControlDirective('max-stale', $stale);
        }

        if (!$response->headers->hasCacheControlDirective('stale-while-revalidate') && null !== $staleWhileRevalidate = $configuration->getStaleWhileRevalidate()) {
            $staleWhileRevalidate = $this->convertToSecondsIfNeeded($staleWhileRevalidate);

            $response->headers->addCacheControlDirective('stale-while-revalidate', $staleWhileRevalidate);
        }

        if (!$response->headers->hasCacheControlDirective('stale-if-error') && null !== $staleIfError = $configuration->getStaleIfError()) {
            $staleIfError = $this->convertToSecondsIfNeeded($staleIfError);

            $response->headers->addCacheControlDirective('stale-if-error', $staleIfError);
        }

        if (!$response->headers->has('Expires') && null !== $configuration->getExpires()) {
            $date = \DateTime::createFromFormat('U', strtotime($configuration->getExpires()), new \DateTimeZone('UTC'));
            $response->setExpires($date);
        }

        if (!$response->headers->has('Vary') && null !== $configuration->getVary()) {
            $response->setVary($configuration->getVary());
        }

        if ($configuration->isPublic()) {
            $response->setPublic();
        }

        if ($configuration->isPrivate()) {
            $response->setPrivate();
        }

        if (!$response->headers->has('Last-Modified') && isset($this->lastModifiedDates[$request])) {
            $response->setLastModified($this->lastModifiedDates[$request]);

            unset($this->lastModifiedDates[$request]);
        }

        if (!$response->headers->has('Etag') && isset($this->etags[$request])) {
            $response->setEtag($this->etags[$request]);

            unset($this->etags[$request]);
        }
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    private function getConfiguration(KernelEvent $event): ?Cache
    {
        $request = $event->getRequest();

        if ($configuration = $request->attributes->get('_cache')) {
            return $configuration;
        }

        if (!$event instanceof ControllerEvent) {
            return null;
        }

        $controller = $event->getController();

        if (!\is_array($controller) && method_exists($controller, '__invoke')) {
            $controller = [$controller, '__invoke'];
        }

        if (!\is_array($controller)) {
            return null;
        }

        $className = \get_class($controller[0]);
        $object = new \ReflectionClass($className);
        $method = $object->getMethod($controller[1]);

        $classConfigurations = array_map(
            function (\ReflectionAttribute $attribute) {
                return $attribute->newInstance();
            },
            $object->getAttributes(Cache::class)
        );
        $methodConfigurations = array_map(
            function (\ReflectionAttribute $attribute) {
                return $attribute->newInstance();
            },
            $method->getAttributes(Cache::class)
        );
        $configurations = array_merge($methodConfigurations, $classConfigurations);

        if (0 === count($configurations)) {
            return null;
        }

        // Use the first encountered configuration, method attributes take precedence over class attributes
        $configuration = $configurations[0];
        $request->attributes->set('_cache', $configuration);

        return $configuration;
    }

    private function getExpressionLanguage()
    {
        if (null === $this->expressionLanguage) {
            if (!class_exists(ExpressionLanguage::class)) {
                throw new \RuntimeException('Unable to use expressions as the Symfony ExpressionLanguage component is not installed.');
            }
            $this->expressionLanguage = new ExpressionLanguage();
        }

        return $this->expressionLanguage;
    }

    /**
     * @param int|string $time Time that can be either expressed in seconds or with relative time format (1 day, 2 weeks, ...)
     *
     * @return int
     */
    private function convertToSecondsIfNeeded($time)
    {
        if (!is_numeric($time)) {
            $now = microtime(true);

            $time = ceil(strtotime($time, $now) - $now);
        }

        return $time;
    }
}
