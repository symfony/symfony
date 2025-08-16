<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Psr\Container\ContainerInterface;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @author Santiago San Martin <sanmartindev@gmail.com>
 */
final class RateLimiterRuntime
{
    public function __construct(
        private readonly ContainerInterface $rateLimiterLocator,
        private readonly RequestStack $requestStack,
        private ?ExpressionLanguage $expressionLanguage = null,
    ) {
    }

    public function rateLimit(string $rateLimiterName, string|Expression|null $key = null, int $tokens = 1): bool
    {
        if (!class_exists(RateLimiterFactory::class)) {
            throw new \LogicException('The "rate_limit()" function requires symfony/rate-limiter. Try running "composer require symfony/rate-limiter".');
        }

        $rateLimiterFactory = $this->rateLimiterLocator->get('limiter.'.$rateLimiterName);
        if (!$rateLimiterFactory instanceof RateLimiterFactory && !$rateLimiterFactory instanceof RateLimiterFactoryInterface) {
            $className = interface_exists(RateLimiterFactoryInterface::class) ? RateLimiterFactoryInterface::class : RateLimiterFactory::class;
            throw new \LogicException(\sprintf('The service "%s" is not an instance of "%s".', $rateLimiterName, $className));
        }

        $limiter = $rateLimiterFactory->create($this->generateKey($key));

        return $limiter->consume($tokens)->isAccepted();
    }

    private function generateKey(string|Expression|null $key): ?string
    {
        if (!$key instanceof Expression) {
            return $key;
        }

        $this->expressionLanguage ??= new ExpressionLanguage();

        return (string) $this->expressionLanguage->evaluate($key, [
            'request' => $this->requestStack->getMainRequest(),
        ]);
    }
}
