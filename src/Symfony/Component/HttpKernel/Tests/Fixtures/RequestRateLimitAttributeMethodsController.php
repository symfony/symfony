<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Fixtures;

use Symfony\Component\HttpKernel\Attribute\RequestRateLimit;

class RequestRateLimitAttributeMethodsController
{
    public function noAttribute()
    {
    }

    #[RequestRateLimit('limiter.test')]
    public function withDefaultBehavior()
    {
    }

    #[RequestRateLimit(['limiter.test_one', 'limiter.test_two'])]
    public function withMultipleFactories()
    {
    }

    #[RequestRateLimit('invalid_service')]
    public function withInvalidService()
    {
    }

    #[RequestRateLimit('limiter.test_one')]
    #[RequestRateLimit('limiter.test_two')]
    public function withMultipleAttributes()
    {
    }

    #[RequestRateLimit('limiter.test', 'POST')]
    public function withPostOnly()
    {
    }

    #[RequestRateLimit('limiter.test', ['GET', 'POST'])]
    public function withGetAndPost()
    {
    }
}
