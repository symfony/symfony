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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Command\DebugCommand;
use Symfony\Component\Messenger\Tests\Fixtures\DummyCommand;
use Symfony\Component\Messenger\Tests\Fixtures\DummyCommandHandler;
use Symfony\Component\Messenger\Tests\Fixtures\DummyCommandWithDescription;
use Symfony\Component\Messenger\Tests\Fixtures\DummyCommandWithDescriptionHandler;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessageInterface;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessageWithAttribute;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessageWithParentWithAttribute;
use Symfony\Component\Messenger\Tests\Fixtures\DummyQuery;
use Symfony\Component\Messenger\Tests\Fixtures\DummyQueryHandler;
use Symfony\Component\Messenger\Tests\Fixtures\MultipleBusesMessage;
use Symfony\Component\Messenger\Tests\Fixtures\MultipleBusesMessageHandler;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class DebugCommandTest extends TestCase
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

    public function testOutput()
    {
        $command = new DebugCommand([
            'command_bus' => [
                DummyCommand::class => [[DummyCommandHandler::class, ['option1' => '1', 'option2' => '2']]],
                DummyCommandWithDescription::class => [[DummyCommandWithDescriptionHandler::class, []]],
                MultipleBusesMessage::class => [[MultipleBusesMessageHandler::class, ['alias' => MultipleBusesMessageHandler::class]]],
            ],
            'query_bus' => [
                DummyQuery::class => [[DummyQueryHandler::class, []]],
                MultipleBusesMessage::class => [[MultipleBusesMessageHandler::class, ['alias' => 'legacy']]],
            ],
        ]);

        $tester = new CommandTester($command);
        $tester->execute([], ['decorated' => false]);

        $this->assertSame(<<<TXT

            Messenger
            =========

            command_bus
            -----------

             The following messages can be dispatched:

             ----------------------------------------------------------------------------------------------------------- 
              Symfony\Component\Messenger\Tests\Fixtures\DummyCommand                                                    
                  handled by Symfony\Component\Messenger\Tests\Fixtures\DummyCommandHandler (when option1=1, option2=2)  
                                                                                                                         
              Used whenever a test needs to show a message with a class description.                                     
              Symfony\Component\Messenger\Tests\Fixtures\DummyCommandWithDescription                                     
                  handled by Symfony\Component\Messenger\Tests\Fixtures\DummyCommandWithDescriptionHandler               
                             Used whenever a test needs to show a message handler with a class description.              
                                                                                                                         
              Symfony\Component\Messenger\Tests\Fixtures\MultipleBusesMessage                                            
                  handled by Symfony\Component\Messenger\Tests\Fixtures\MultipleBusesMessageHandler                      
                                                                                                                         
             ----------------------------------------------------------------------------------------------------------- 

            query_bus
            ---------

             The following messages can be dispatched:

             ----------------------------------------------------------------------------------------------------------- 
              Symfony\Component\Messenger\Tests\Fixtures\DummyQuery                                                      
                  handled by Symfony\Component\Messenger\Tests\Fixtures\DummyQueryHandler                                
                                                                                                                         
              Symfony\Component\Messenger\Tests\Fixtures\MultipleBusesMessage                                            
                  handled by Symfony\Component\Messenger\Tests\Fixtures\MultipleBusesMessageHandler (when alias=legacy)  
                                                                                                                         
             ----------------------------------------------------------------------------------------------------------- 


            TXT,
            $tester->getDisplay(true)
        );

        $tester->execute(['bus' => 'query_bus'], ['decorated' => false]);

        $this->assertSame(<<<TXT

            Messenger
            =========

            query_bus
            ---------

             The following messages can be dispatched:

             ----------------------------------------------------------------------------------------------------------- 
              Symfony\Component\Messenger\Tests\Fixtures\DummyQuery                                                      
                  handled by Symfony\Component\Messenger\Tests\Fixtures\DummyQueryHandler                                
                                                                                                                         
              Symfony\Component\Messenger\Tests\Fixtures\MultipleBusesMessage                                            
                  handled by Symfony\Component\Messenger\Tests\Fixtures\MultipleBusesMessageHandler (when alias=legacy)  
                                                                                                                         
             ----------------------------------------------------------------------------------------------------------- 


            TXT,
            $tester->getDisplay(true)
        );
    }

    public function testOutputWithoutMessages()
    {
        $command = new DebugCommand(['command_bus' => [], 'query_bus' => []]);

        $tester = new CommandTester($command);
        $tester->execute([], ['decorated' => false]);

        $this->assertSame(<<<TXT

            Messenger
            =========

            command_bus
            -----------

             [WARNING] No handled message found in bus "command_bus".                                                               

            query_bus
            ---------

             [WARNING] No handled message found in bus "query_bus".                                                                 


            TXT,
            $tester->getDisplay(true)
        );
    }

    public function testOutputIncludesRoutingInformationAndGlobalTransportRules()
    {
        $command = new DebugCommand(
            [
                'command_bus' => [DummyCommand::class => [[DummyCommandHandler::class, []]]],
                'event_bus' => [DummyCommand::class => [[DummyCommandHandler::class, []]]],
            ],
            [
                DummyCommand::class => ['messenger.transport.async'],
                DummyMessageInterface::class => ['messenger.transport.async'],
                'App\\Message\\*' => ['messenger.transport.async'],
                '*' => ['messenger.transport.fallback'],
            ],
            [
                'async' => 'messenger.transport.async',
                'fallback' => 'messenger.transport.fallback',
                'unused' => 'messenger.transport.unused',
            ],
        );

        $tester = new CommandTester($command);
        $tester->execute([], ['decorated' => false]);
        $display = $tester->getDisplay(true);

        $this->assertSame(2, substr_count($display, 'routed to async'));
        $this->assertSame(1, substr_count($display, 'Transports'));
        $this->assertStringContainsString(DummyMessageInterface::class.' (interface, matches implementers)', $display);
        $this->assertStringContainsString('App\\Message\\* (namespace)', $display);
        $this->assertStringContainsString('* (fallback for messages with no other route)', $display);
        $this->assertStringContainsString('unused', $display);
        $this->assertStringContainsString('No routing rules.', $display);
        $this->assertStringNotContainsString('[WARNING]', $display);
        $this->assertSame(1, substr_count($display, 'TransportNamesStamp can override this routing at dispatch time.'));
    }

    public function testOutputForMessageResolvesHandlersAndRouting()
    {
        $command = new DebugCommand(
            [
                'command_bus' => [DummyMessageInterface::class => [[DummyCommandHandler::class, []]]],
            ],
            [
                DummyMessageInterface::class => ['messenger.transport.configured'],
                DummyCommand::class => ['messenger.transport.configured'],
            ],
            [
                'configured' => 'messenger.transport.configured',
                'first_sender' => 'messenger.transport.first_sender',
                'second_sender' => 'messenger.transport.second_sender',
                'failed' => 'messenger.transport.failed',
                'unrelated_failure' => 'messenger.transport.unrelated_failure',
            ],
            [
                DummyMessageWithAttribute::class => ['first_sender', 'second_sender'],
            ],
            [
                'configured' => 'messenger.transport.failed',
                'first_sender' => 'messenger.transport.unrelated_failure',
            ],
        );

        $tester = new CommandTester($command);
        $tester->execute(['--message' => DummyMessageWithAttribute::class], ['decorated' => false]);
        $display = $tester->getDisplay(true);

        $this->assertStringContainsString(DummyMessageWithAttribute::class, $display);
        $this->assertStringContainsString('handled by '.DummyCommandHandler::class, $display);
        $this->assertStringContainsString('routed to configured', $display);
        $this->assertStringContainsString(DummyMessageInterface::class.' (interface, matches implementers)', $display);
        $this->assertStringNotContainsString(DummyCommand::class.\PHP_EOL, $display);
        $this->assertStringNotContainsString('routed to first_sender', $display);
        $this->assertStringNotContainsString('routed to second_sender', $display);
        $this->assertStringNotContainsString('first_sender', $display);
        $this->assertStringNotContainsString('second_sender', $display);
        $this->assertStringContainsString('failed messages are routed to failed', $display);
        $this->assertStringNotContainsString('unrelated_failure', $display);
    }

    public function testOutputIncludesEffectiveAttributeRoutingRule()
    {
        $command = new DebugCommand(
            ['command_bus' => []],
            [],
            [
                'first_sender' => 'messenger.transport.first_sender',
                'second_sender' => 'messenger.transport.second_sender',
            ],
            [
                DummyMessageWithAttribute::class => ['first_sender', 'second_sender'],
            ],
        );

        $tester = new CommandTester($command);
        $tester->execute([], ['decorated' => false]);

        $this->assertSame(2, substr_count($tester->getDisplay(true), DummyMessageWithAttribute::class.' (from #[AsMessage])'));
    }

    public function testOutputIncludesFailureTransportRoutes()
    {
        $command = new DebugCommand(
            ['command_bus' => []],
            [DummyCommand::class => ['messenger.transport.async']],
            [
                'async' => 'messenger.transport.async',
                'audit' => 'messenger.transport.audit',
                'failed' => 'messenger.transport.failed',
            ],
            [],
            [
                'async' => 'messenger.transport.failed',
                'audit' => 'failed',
                'failed' => 'failed',
            ],
        );

        $tester = new CommandTester($command);
        $tester->execute([], ['decorated' => false]);
        $display = $tester->getDisplay(true);

        $this->assertSame(2, substr_count($display, 'failed messages are routed to failed'));
        $this->assertStringContainsString("audit\n    No routing rules.\n    failed messages are routed to failed", $display);
    }

    public function testOutputForMessageIncludesOnlyEffectiveWildcardRule()
    {
        $command = new DebugCommand(
            ['command_bus' => []],
            [
                'Symfony\\Component\\Messenger\\Tests\\Fixtures\\*' => ['messenger.transport.namespace'],
                '*' => ['messenger.transport.fallback'],
                DummyQuery::class => ['messenger.transport.unrelated'],
            ],
            [
                'namespace' => 'messenger.transport.namespace',
                'fallback' => 'messenger.transport.fallback',
                'unrelated' => 'messenger.transport.unrelated',
            ],
        );

        $tester = new CommandTester($command);
        $tester->execute(['--message' => DummyCommand::class], ['decorated' => false]);
        $display = $tester->getDisplay(true);

        $this->assertStringContainsString('routed to namespace', $display);
        $this->assertStringContainsString('Symfony\\Component\\Messenger\\Tests\\Fixtures\\* (namespace)', $display);
        $this->assertStringNotContainsString('fallback', $display);
        $this->assertStringNotContainsString('unrelated', $display);
    }

    public function testOutputForMessageIncludesOnlyEffectiveAttributeRules()
    {
        $command = new DebugCommand(
            ['command_bus' => []],
            [],
            [
                'first_sender' => 'messenger.transport.first_sender',
                'second_sender' => 'messenger.transport.second_sender',
                'third_sender' => 'messenger.transport.third_sender',
                'unrelated' => 'messenger.transport.unrelated',
            ],
            [
                DummyMessageWithAttribute::class => ['first_sender', 'second_sender'],
                DummyMessageWithParentWithAttribute::class => ['third_sender'],
                DummyCommand::class => ['unrelated'],
            ],
        );

        $tester = new CommandTester($command);
        $tester->execute(['--message' => DummyMessageWithParentWithAttribute::class], ['decorated' => false]);
        $display = $tester->getDisplay(true);

        $this->assertSame(2, substr_count($display, DummyMessageWithAttribute::class.' (from #[AsMessage])'));
        $this->assertSame(2, substr_count($display, DummyMessageWithParentWithAttribute::class));
        $this->assertStringNotContainsString('unrelated', $display);
    }

    public function testOutputForMessageAcceptsALeadingBackslash()
    {
        $command = new DebugCommand(
            ['command_bus' => [DummyCommand::class => [[DummyCommandHandler::class, []]]]],
            [DummyCommand::class => ['messenger.transport.async']],
            ['async' => 'messenger.transport.async'],
        );

        $tester = new CommandTester($command);
        $tester->execute(['--message' => '\\'.DummyCommand::class], ['decorated' => false]);
        $display = $tester->getDisplay(true);

        $this->assertStringContainsString('handled by '.DummyCommandHandler::class, $display);
        $this->assertStringContainsString('routed to async', $display);
        $this->assertStringNotContainsString('not routed', $display);
    }

    public function testOutputForMessageResolvesAttributeRulesOfUnregisteredMessages()
    {
        $command = new DebugCommand(
            ['command_bus' => []],
            [],
            [
                'first_sender' => 'messenger.transport.first_sender',
                'second_sender' => 'messenger.transport.second_sender',
            ],
        );

        $tester = new CommandTester($command);
        $tester->execute(['--message' => DummyMessageWithAttribute::class], ['decorated' => false]);
        $display = $tester->getDisplay(true);

        $this->assertStringContainsString('routed to first_sender', $display);
        $this->assertStringContainsString('routed to second_sender', $display);
        $this->assertSame(2, substr_count($display, DummyMessageWithAttribute::class.' (from #[AsMessage])'));
        $this->assertStringNotContainsString('No routing rules apply to this message.', $display);
    }

    public function testOutputDoesNotListAttributeRuleOverriddenByConfigurationUsingSameTransport()
    {
        $command = new DebugCommand(
            ['command_bus' => []],
            [DummyMessageInterface::class => ['first_sender']],
            [
                'first_sender' => 'messenger.transport.first_sender',
                'second_sender' => 'messenger.transport.second_sender',
            ],
            [
                DummyMessageWithAttribute::class => ['first_sender', 'second_sender'],
            ],
        );

        $tester = new CommandTester($command);
        $tester->execute([], ['decorated' => false]);

        $this->assertStringNotContainsString(DummyMessageWithAttribute::class.' (from #[AsMessage])', $tester->getDisplay(true));
    }

    public function testOutputForUnroutedMessageIncludesStampCaveat()
    {
        $command = new DebugCommand(['command_bus' => []]);
        $tester = new CommandTester($command);

        $tester->execute(['--message' => DummyCommand::class], ['decorated' => false]);
        $display = $tester->getDisplay(true);

        $this->assertStringContainsString('not routed', $display);
        $this->assertStringContainsString('TransportNamesStamp can override this routing at dispatch time.', $display);
        $this->assertStringContainsString('No transports are configured.', $display);
    }

    public function testOutputForUnroutedMessageDoesNotListUnrelatedTransports()
    {
        $command = new DebugCommand(
            ['command_bus' => []],
            [DummyQuery::class => ['messenger.transport.async']],
            ['async' => 'messenger.transport.async'],
        );
        $tester = new CommandTester($command);

        $tester->execute(['--message' => DummyCommand::class], ['decorated' => false]);
        $display = $tester->getDisplay(true);

        $this->assertStringContainsString('not routed', $display);
        $this->assertStringContainsString('No routing rules apply to this message.', $display);
        $this->assertStringNotContainsString('async', $display);
    }

    public function testExceptionOnUnknownBusArgument()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Bus "unknown_bus" does not exist. Known buses are "command_bus", "query_bus".');
        $command = new DebugCommand(['command_bus' => [], 'query_bus' => []]);

        $tester = new CommandTester($command);
        $tester->execute(['bus' => 'unknown_bus'], ['decorated' => false]);
    }

    #[DataProvider('provideCompletionSuggestions')]
    public function testComplete(array $input, array $expectedSuggestions)
    {
        $command = new DebugCommand(
            ['command_bus' => [DummyCommand::class => []], 'query_bus' => []],
            [DummyMessageInterface::class => [], '*' => [], 'App\\Message\\*' => []],
            [],
            [DummyMessageWithAttribute::class => []],
        );
        $application = new Application();
        $application->addCommand($command);
        $tester = new CommandCompletionTester($application->get('debug:messenger'));
        $this->assertSame($expectedSuggestions, $tester->complete($input));
    }

    public static function provideCompletionSuggestions(): iterable
    {
        yield 'bus' => [
            [''],
            ['command_bus', 'query_bus'],
        ];

        yield 'message' => [
            ['--message='],
            [DummyCommand::class, DummyMessageInterface::class, DummyMessageWithAttribute::class],
        ];
    }
}
