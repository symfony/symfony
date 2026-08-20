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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\Profiler;

/**
 * ProfilerListener collects data for the current request by listening to the kernel events.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class ProfilerListener implements EventSubscriberInterface
{
    private ?\Throwable $exception = null;
    /** @var \SplObjectStorage<Request, Profile> */
    private \SplObjectStorage $profiles;
    /** @var \SplObjectStorage<Request, Request|null> */
    private \SplObjectStorage $parents;
    private ?string $excludedPathsPattern = null;
    /** @var array<int, string|null> */
    private array $excludedHttpCodePatterns = [];

    /**
     * @param bool                     $onlyException     True if the profiler only collects data when an exception occurs, false otherwise
     * @param bool                     $onlyMainRequests  True if the profiler only collects data when the request is the main request, false otherwise
     * @param list<string>             $excludedPaths     Regular expressions matched against the url-decoded path info of the requests that must not be profiled
     * @param array<int, list<string>> $excludedHttpCodes Maps HTTP status codes to regular expressions restricting each exclusion to matching path infos; an empty list excludes every response having that status code
     */
    public function __construct(
        private Profiler $profiler,
        private RequestStack $requestStack,
        private ?RequestMatcherInterface $matcher = null,
        private bool $onlyException = false,
        private bool $onlyMainRequests = false,
        private ?string $collectParameter = null,
        array $excludedPaths = [],
        array $excludedHttpCodes = [],
    ) {
        $this->profiles = new \SplObjectStorage();
        $this->parents = new \SplObjectStorage();

        if ($excludedPaths) {
            $this->excludedPathsPattern = self::compilePattern($excludedPaths, 'excludedPaths');
        }

        foreach ($excludedHttpCodes as $code => $paths) {
            $this->excludedHttpCodePatterns[$code] = $paths ? self::compilePattern($paths, 'excludedHttpCodes') : null;
        }
    }

    /**
     * Handles the onKernelException event.
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        if ($this->onlyMainRequests && !$event->isMainRequest()) {
            return;
        }

        $this->exception = $event->getThrowable();
    }

    /**
     * Handles the onKernelResponse event.
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if ($this->onlyMainRequests && !$event->isMainRequest()) {
            return;
        }

        if ($this->onlyException && null === $this->exception) {
            return;
        }

        $request = $event->getRequest();

        $exception = $this->exception;
        $this->exception = null;

        if ($this->isExcluded($request, $event->getResponse())) {
            return;
        }

        if (null !== $this->collectParameter && null !== $collectParameterValue = $request->attributes->get($this->collectParameter) ?? $request->query->get($this->collectParameter) ?? $request->request->get($this->collectParameter)) {
            filter_var($collectParameterValue, \FILTER_VALIDATE_BOOL) ? $this->profiler->enable() : $this->profiler->disable();
        }

        if (null !== $this->matcher && !$this->matcher->matches($request)) {
            return;
        }

        $session = !$request->attributes->getBoolean('_stateless') && $request->hasPreviousSession() ? $request->getSession() : null;

        if ($session instanceof Session) {
            $usageIndexValue = $usageIndexReference = &$session->getUsageIndex();
            $usageIndexReference = \PHP_INT_MIN;
        }

        try {
            if (!$profile = $this->profiler->collect($request, $event->getResponse(), $exception)) {
                return;
            }
        } finally {
            if ($session instanceof Session) {
                $usageIndexReference = $usageIndexValue;
            }
        }

        $this->profiles[$request] = $profile;

        $this->parents[$request] = $this->requestStack->getParentRequest();
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        // attach children to parents
        foreach ($this->profiles as $request) {
            if (null !== $parentRequest = $this->parents[$request]) {
                if (isset($this->profiles[$parentRequest])) {
                    $this->profiles[$parentRequest]->addChild($this->profiles[$request]);
                }
            }
        }

        // save profiles
        foreach ($this->profiles as $request) {
            $this->profiler->saveProfile($this->profiles[$request]);
        }

        $this->reset();
    }

    public function reset(): void
    {
        $this->profiles = new \SplObjectStorage();
        $this->parents = new \SplObjectStorage();
        $this->exception = null;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // low priority to come after listeners that could change the response
            KernelEvents::RESPONSE => ['onKernelResponse', -1024],
            KernelEvents::EXCEPTION => ['onKernelException', 0],
            KernelEvents::TERMINATE => ['onKernelTerminate', -1024],
        ];
    }

    private function isExcluded(Request $request, Response $response): bool
    {
        if (null === $this->excludedPathsPattern && !$this->excludedHttpCodePatterns) {
            return false;
        }

        $pathInfo = rawurldecode($request->getPathInfo());

        if (null !== $this->excludedPathsPattern && preg_match($this->excludedPathsPattern, $pathInfo)) {
            return true;
        }

        if (!\array_key_exists($code = $response->getStatusCode(), $this->excludedHttpCodePatterns)) {
            return false;
        }

        $pattern = $this->excludedHttpCodePatterns[$code];

        return null === $pattern || preg_match($pattern, $pathInfo);
    }

    /**
     * @param list<string> $regexps
     */
    private static function compilePattern(array $regexps, string $argument): string
    {
        $pattern = '{('.implode('|', $regexps).')}';

        if (false === @preg_match($pattern, '')) {
            throw new \LogicException(\sprintf('Invalid regular expression in the "$%s" argument of "%s": "%s".', $argument, self::class, implode('", "', $regexps)));
        }

        return $pattern;
    }
}
