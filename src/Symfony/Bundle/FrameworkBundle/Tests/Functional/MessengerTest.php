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

        self::assertMessagesCount(6);
        self::assertMessagesCount(2, busName: 'event.bus');
        self::assertMessagesCount(4, busName: 'query.bus');

        self::assertMessagesCount(2, messageFQCN: DummyCommand::class);
        self::assertMessagesCount(3, messageFQCN: DummyQuery::class);
        self::assertMessagesCount(1, messageFQCN: DummyMessage::class);

        /** @var DummyMessage[] $dummyMessages */
        $dummyMessages = self::getDispatchedMessages(messageFQCN: DummyMessage::class);
        self::assertCount(1, $dummyMessages);
        self::assertStringContainsString('dummy message text', $dummyMessages[0]->getMessage());

        $messagesFromQueryBus = self::getDispatchedMessages(busName: 'query.bus');
        self::assertCount(4, $messagesFromQueryBus);

        self::assertInstanceOf(DummyMessage::class, $messagesFromQueryBus[0]);

        /* @var DummyQuery $firstQuery */
        self::assertInstanceOf(DummyQuery::class, $firstQuery = $messagesFromQueryBus[1]);
        self::assertStringContainsString('First Dummy Query message', $firstQuery->getMessage());
    }
}
