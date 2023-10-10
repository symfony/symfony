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

use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\DummyCommand;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\DummyMessage;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\DummyQuery;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/*
 * @author Marilena Ruffelaere <marilena.ruffelaere@gmail.com>
 */

class MessengerController
{
    public function indexAction(MessageBusInterface $bus, MessageBusInterface $queryBus): Response
    {
        $queryBus->dispatch(new Envelope(message: new DummyMessage('dummy message text'), stamps: []));

        $queryBus->dispatch(new DummyQuery('First Dummy Query message'));

        $bus->dispatch(new DummyCommand('First Dummy Command message'));

        $queryBus->dispatch(new DummyQuery('Still searching?'));

        $queryBus->dispatch(new DummyQuery('Stop searching, Symfony is amazing!'));

        $bus->dispatch(new DummyCommand('Second Dummy Command message'));

        return new Response();
    }
}
