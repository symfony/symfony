<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

/*
 * @author Marilena Ruffelaere <marilena.ruffelaere@gmail.com>
 */

use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\DummyCommand;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\DummyMessage;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\DummyQuery;

final class MessengerTest extends AbstractWebTestCase
{
    public function testMessengerAssertion()
    {
        $client = $this->createClient(['test_case' => 'Messenger', 'root_config' => 'config.yml', 'debug' => true]);
        $response = $client->request('GET', '/send_messenger_message');

        $this->assertMessagesByBusCount(6);
        $this->assertMessagesByBusCount(2, 'event.bus');
        $this->assertMessagesByBusCount(4, 'query.bus');

        $this->assertMessagesOfClassCount(2, DummyCommand::class);
        $this->assertMessagesOfClassCount(3, DummyQuery::class);
        $this->assertMessagesOfClassCount(1, DummyMessage::class);

        /** @var DummyMessage[] $dummyMessages */
        $dummyMessages = $this::getDispatchedMessagesByClassName(DummyMessage::class);
        self::assertCount(1, $dummyMessages);
        self::assertStringContainsString('dummy message text', $dummyMessages[0]->getMessage());

        $messagesFromQueryBus = $this::getDispatchedMessagesByBusName('query.bus');
        self::assertCount(4, $messagesFromQueryBus);

        self::assertInstanceOf(DummyMessage::class, $messagesFromQueryBus[0]);

        /* @var DummyQuery $firstQuery */
        self::assertInstanceOf(DummyQuery::class, $firstQuery = $messagesFromQueryBus[1]);
        self::assertStringContainsString('First Dummy Query message', $firstQuery->getMessage());
    }
}
