<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Command\ShowMessagesCommand;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

class ShowMessagesCommandTest extends TestCase
{
    private string|false $colSize;

    protected function setUp(): void
    {
        $this->colSize = getenv('COLUMNS');
        putenv('COLUMNS='.(119 + \strlen(\PHP_EOL)));
    }

    protected function tearDown(): void
    {
        putenv($this->colSize ? 'COLUMNS='.$this->colSize : 'COLUMNS');
    }

    public function testListMessages()
    {
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('all')->with(50)->willReturn([
            new Envelope(new \stdClass(), [new TransportMessageIdStamp(15)]),
            new Envelope(new DummyMessage('Hello'), [new TransportMessageIdStamp(16)]),
        ]);

        $command = new ShowMessagesCommand(new ServiceLocator(['async' => static fn () => $receiver]), ['async']);

        $tester = new CommandTester($command);
        $tester->execute(['transport' => 'async']);

        $display = $tester->getDisplay(true);
        $this->assertStringContainsString('15   stdClass', $display);
        $this->assertStringContainsString('16   '.DummyMessage::class, $display);
        $this->assertStringContainsString('Run messenger:show async {id} -vv to see message details.', $display);
    }

    public function testListMessagesReturnsNoMessagesFound()
    {
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('all')->with(50)->willReturn([]);

        $command = new ShowMessagesCommand(new ServiceLocator(['async' => static fn () => $receiver]));

        $tester = new CommandTester($command);
        $tester->execute(['transport' => 'async']);

        $this->assertStringContainsString('[OK] No pending messages were found.', $tester->getDisplay(true));
    }

    public function testListMessagesReturnsPaginatedMessages()
    {
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('all')->with(1)->willReturn([
            new Envelope(new \stdClass(), [new TransportMessageIdStamp(15)]),
        ]);

        $command = new ShowMessagesCommand(new ServiceLocator(['async' => static fn () => $receiver]));

        $tester = new CommandTester($command);
        $tester->execute(['transport' => 'async', '--max' => 1]);

        $this->assertStringContainsString('Showing first 1 messages.', $tester->getDisplay(true));
    }

    public function testListMessagesReturnsFilteredByClassMessage()
    {
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('all')->with(50)->willReturn([
            new Envelope(new \stdClass(), [new TransportMessageIdStamp(15)]),
            new Envelope(new DummyMessage('Hello'), [new TransportMessageIdStamp(16)]),
        ]);

        $command = new ShowMessagesCommand(new ServiceLocator(['async' => static fn () => $receiver]));

        $tester = new CommandTester($command);
        $tester->execute(['transport' => 'async', '--class-filter' => DummyMessage::class]);

        $display = $tester->getDisplay(true);
        $this->assertStringContainsString(\sprintf('Displaying only \'%s\' messages', DummyMessage::class), $display);
        $this->assertStringContainsString('16   '.DummyMessage::class, $display);
        $this->assertStringContainsString('Showing 1 message(s).', $display);
        $this->assertStringNotContainsString('stdClass', $display);
    }

    public function testStatsIgnoresDefaultMaxAndCountsAllMessages()
    {
        $envelope = new Envelope(new \stdClass(), [new TransportMessageIdStamp(15)]);
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('all')->with(null)->willReturn([$envelope, $envelope, $envelope]);

        $command = new ShowMessagesCommand(new ServiceLocator(['async' => static fn () => $receiver]));

        $tester = new CommandTester($command);
        $tester->execute(['transport' => 'async', '--stats' => true]);

        $this->assertStringContainsString('stdClass   3', $tester->getDisplay(true));
    }

    public function testStatsHonorsExplicitMax()
    {
        $envelope = new Envelope(new \stdClass(), [new TransportMessageIdStamp(15)]);
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('all')->with(10)->willReturn([$envelope]);

        $command = new ShowMessagesCommand(new ServiceLocator(['async' => static fn () => $receiver]));

        $tester = new CommandTester($command);
        $tester->execute(['transport' => 'async', '--stats' => true, '--max' => 10]);

        $this->assertStringContainsString('stdClass   1', $tester->getDisplay(true));
    }

    public function testStatsAppliesClassFilter()
    {
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('all')->with(null)->willReturn([
            new Envelope(new \stdClass(), [new TransportMessageIdStamp(15)]),
            new Envelope(new DummyMessage('Hello'), [new TransportMessageIdStamp(16)]),
        ]);

        $command = new ShowMessagesCommand(new ServiceLocator(['async' => static fn () => $receiver]));

        $tester = new CommandTester($command);
        $tester->execute(['transport' => 'async', '--stats' => true, '--class-filter' => DummyMessage::class]);

        $display = $tester->getDisplay(true);
        $this->assertStringContainsString(DummyMessage::class.'   1', $display);
        $this->assertStringNotContainsString('stdClass', $display);
    }

    public function testShowMessage()
    {
        $envelope = new Envelope(new DummyMessage('Hello'), [new TransportMessageIdStamp(15)]);
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('find')->with(15)->willReturn($envelope);

        $command = new ShowMessagesCommand(new ServiceLocator(['async' => static fn () => $receiver]));

        $tester = new CommandTester($command);
        $tester->execute(['transport' => 'async', 'id' => 15]);

        $display = $tester->getDisplay(true);
        $this->assertStringContainsString('Message Details', $display);
        $this->assertStringContainsString('Class        '.DummyMessage::class, $display);
        $this->assertStringContainsString('Message Id   15', $display);
        $this->assertStringContainsString('Re-run command with -vv to see more message details.', $display);
    }

    public function testShowMessageDumpsTheMessageWhenVeryVerbose()
    {
        $envelope = new Envelope(new DummyMessage('Hello'), [new TransportMessageIdStamp(15)]);
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('find')->with(15)->willReturn($envelope);

        $command = new ShowMessagesCommand(new ServiceLocator(['async' => static fn () => $receiver]));

        $tester = new CommandTester($command);
        $tester->execute(['transport' => 'async', 'id' => 15], ['verbosity' => OutputInterface::VERBOSITY_VERY_VERBOSE]);

        $display = $tester->getDisplay(true);
        $this->assertStringContainsString('Message Details', $display);
        $this->assertStringContainsString('-message: "Hello"', $display);
    }

    public function testThrowsWhenMessageIsNotFound()
    {
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('find')->with(15)->willReturn(null);

        $command = new ShowMessagesCommand(new ServiceLocator(['async' => static fn () => $receiver]));

        $this->expectExceptionMessage('The message "15" was not found.');

        $tester = new CommandTester($command);
        $tester->execute(['transport' => 'async', 'id' => 15]);
    }

    public function testThrowsWhenTransportDoesNotExist()
    {
        $command = new ShowMessagesCommand(new ServiceLocator([]), ['async', 'failed']);

        $this->expectExceptionMessage('The "nope" transport does not exist. Valid transports are: async, failed.');

        $tester = new CommandTester($command);
        $tester->execute(['transport' => 'nope']);
    }

    public function testThrowsWhenReceiverIsNotListable()
    {
        $receiver = $this->createStub(ReceiverInterface::class);

        $command = new ShowMessagesCommand(new ServiceLocator(['async' => static fn () => $receiver]));

        $this->expectExceptionMessage('The "async" transport does not support listing or showing specific messages.');

        $tester = new CommandTester($command);
        $tester->execute(['transport' => 'async']);
    }

    public function testThrowsWhenNoTransportIsGiven()
    {
        $command = new ShowMessagesCommand(new ServiceLocator([]));

        $this->expectExceptionMessage('Please pass the name of the transport to inspect.');

        $tester = new CommandTester($command);
        $tester->execute([]);
    }

    public function testThrowsWhenIdIsCombinedWithStats()
    {
        $command = new ShowMessagesCommand(new ServiceLocator([]));

        $this->expectExceptionMessage('You cannot specify a message id when using the "--stats" or "--class-filter" options.');

        $tester = new CommandTester($command);
        $tester->execute(['transport' => 'async', 'id' => 15, '--stats' => true]);
    }

    public function testThrowsWhenIdIsCombinedWithClassFilter()
    {
        $command = new ShowMessagesCommand(new ServiceLocator([]));

        $this->expectExceptionMessage('You cannot specify a message id when using the "--stats" or "--class-filter" options.');

        $tester = new CommandTester($command);
        $tester->execute(['transport' => 'async', 'id' => 15, '--class-filter' => 'App\Message\SendEmail']);
    }

    public function testChooseTransportInteractively()
    {
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('all')->with(50)->willReturn([]);

        $command = new ShowMessagesCommand(new ServiceLocator(['async' => static fn () => $receiver]), ['async', 'failed']);

        $tester = new CommandTester($command);
        $tester->setInputs([0]);
        $tester->execute([]);

        $display = $tester->getDisplay(true);
        $this->assertStringContainsString('Which transport do you want to inspect?', $display);
        $this->assertStringContainsString('[OK] No pending messages were found.', $display);
    }

    public function testCompleteTransport()
    {
        $command = new ShowMessagesCommand(new ServiceLocator([]), ['async', 'failed']);

        $tester = new CommandCompletionTester($command);

        $this->assertSame(['async', 'failed'], $tester->complete(['']));
    }
}
