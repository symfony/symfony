<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\RateLimit;
use Symfony\Component\RateLimiter\Policy\FixedWindowPolicy;

class RateLimiterController
{
    #[RateLimit('tickets')]
    public function tickets(): Response
    {
        return new Response('ticket');
    }

    #[RateLimit('own_pool')]
    public function ownPool(): Response
    {
        return new Response('ticket');
    }

    #[RateLimit(new FixedWindowPolicy(5, '1 hour'))]
    public function inline(): Response
    {
        return new Response('ticket');
    }
}
