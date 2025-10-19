<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\RateLimiter;

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\RateLimiter\AbstractRequestRateLimiter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * A rate limiter that limits requests based on the controller and method.
 *
 * @author Santiago San Martin <sanmartindev@gmail.com>
 *
 * @internal
 */
final class RequestRateLimiter extends AbstractRequestRateLimiter
{
    /** @var list<RateLimiterFactory> */
    private array $rateLimiterFactories = [];

    /**
     * @param non-empty-string $secret A secret to use for hashing the IP address and controller
     */
    public function __construct(
        private readonly ContainerInterface $rateLimiterFactoryLocator,
        #[\SensitiveParameter] private readonly string $secret,
    ) {
        if (!$secret) {
            throw new \InvalidArgumentException('A non-empty secret is required.');
        }
    }

    /**
     * @param string[] $rateLimiterFactoriesIds
     */
    public function addRateLimiterFactories(array $rateLimiterFactoriesIds): self
    {
        $expectedClass = interface_exists(RateLimiterFactoryInterface::class) ? RateLimiterFactoryInterface::class : RateLimiterFactory::class;

        foreach ($rateLimiterFactoriesIds as $rateLimiterFactoryId) {
            $rateLimiterFactory = $this->rateLimiterFactoryLocator->get($rateLimiterFactoryId);
            if (!$rateLimiterFactory instanceof $expectedClass) {
                throw new \InvalidArgumentException(\sprintf('The service "%s" is not an instance of "%s".', $rateLimiterFactoryId, $expectedClass));
            }

            $this->rateLimiterFactories[] = $rateLimiterFactory;
        }

        return $this;
    }

    protected function getLimiters(Request $request): array
    {
        $limiters = [];
        $hash = $this->getHash($request);

        foreach ($this->rateLimiterFactories as $rateLimiterFactory) {
            $limiters[] = $rateLimiterFactory->create($hash);
        }

        return $limiters;
    }

    private function getHash(Request $request): string
    {
        $data = $this->parseController($request->attributes->get('_controller')).'-'.$request->getClientIp();

        return strtr(substr(base64_encode(hash_hmac('sha256', $data, $this->secret, true)), 0, 8), '/+', '._');
    }

    /**
     * @return string An string of controller data
     */
    private function parseController(array|object|string|null $controller): string
    {
        if (\is_string($controller) && str_contains($controller, '::')) {
            $controller = explode('::', $controller);
        }

        if (\is_array($controller)) {
            $class = \is_object($controller[0]) ? get_debug_type($controller[0]) : $controller[0];

            return $class.'::'.$controller[1];
        }

        if ($controller instanceof \Closure) {
            $r = new \ReflectionFunction($controller);

            if ($r->isAnonymous()) {
                return $r->getName();
            }

            if ($class = $r->getClosureCalledClass()) {
                return $class.'::'.$r->name;
            }

            return $r->name;
        }

        if (\is_object($controller)) {
            $r = new \ReflectionClass($controller);

            return $r->getName();
        }

        return \is_string($controller) ? $controller : 'n/a';
    }
}
