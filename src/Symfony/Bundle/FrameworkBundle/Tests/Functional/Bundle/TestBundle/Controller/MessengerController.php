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

use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\BarMessage;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\FooMessage;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class MessengerController
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function dispatch(Request $request, string $message): Response
    {
        $this->bus->dispatch('bar' === $message ? new BarMessage() : new FooMessage(), $request->query->has('delay') ? [new DelayStamp($request->query->getInt('delay'))] : []);

        return new RedirectResponse('/messenger');
    }

    public function idle(): Response
    {
        return new Response('');
    }
}
