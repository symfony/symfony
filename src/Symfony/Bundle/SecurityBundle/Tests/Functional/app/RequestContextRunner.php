<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\app;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Runs a callable inside a request context, for the request-scoped services it needs.
 */
class RequestContextRunner
{
    /** @var callable|null */
    public $callable;

    public function __invoke(RequestEvent $event): void
    {
        if (!$callable = $this->callable) {
            return;
        }

        $this->callable = null;
        $callable();
        $event->setResponse(new Response(''));
    }
}
