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
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Command\DebugCommand;
use Symfony\Component\Messenger\Tests\Fixtures\DummyCommand;
use Symfony\Component\Messenger\Tests\Fixtures\DummyCommandHandler;
use Symfony\Component\Messenger\Tests\Fixtures\DummyQuery;
use Symfony\Component\Messenger\Tests\Fixtures\DummyQueryHandler;
use Symfony\Component\Messenger\Tests\Fixtures\MultipleBusesMessage;
use Symfony\Component\Messenger\Tests\Fixtures\MultipleBusesMessageHandler;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class DebugCommandTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('COLUMNS='.(119 + \strlen(PHP_EOL)));
    }

    protected function tearDown(): void
    {
        putenv('COLUMNS=');
    }

    public function testOutput()
    {
        $command = new DebugCommand([
            'command_bus' => [
                DummyCommand::class => [[DummyCommandHandler::class, []]],
                MultipleBusesMessage::class => [[MultipleBusesMessageHandler::class, []]],
            ],
            'query_bus' => [
                DummyQuery::class => [[DummyQueryHandler::class, []]],
                MultipleBusesMessage::class => [[MultipleBusesMessageHandler::class, []]],
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

 --------------------------------------------------------------------------------------- 
  Symfony\Component\Messenger\Tests\Fixtures\DummyCommand                                
      handled by Symfony\Component\Messenger\Tests\Fixtures\DummyCommandHandler          
  Symfony\Component\Messenger\Tests\Fixtures\MultipleBusesMessage                        
      handled by Symfony\Component\Messenger\Tests\Fixtures\MultipleBusesMessageHandler  
 --------------------------------------------------------------------------------------- 

query_bus
---------

 The following messages can be dispatched:

 --------------------------------------------------------------------------------------- 
  Symfony\Component\Messenger\Tests\Fixtures\DummyQuery                                  
      handled by Symfony\Component\Messenger\Tests\Fixtures\DummyQueryHandler            
  Symfony\Component\Messenger\Tests\Fixtures\MultipleBusesMessage                        
      handled by Symfony\Component\Messenger\Tests\Fixtures\MultipleBusesMessageHandler  
 --------------------------------------------------------------------------------------- 


TXT
            , $tester->getDisplay(true)
        );

        $tester->execute(['bus' => 'query_bus'], ['decorated' => false]);

        $this->assertSame(<<<TXT

Messenger
=========

query_bus
---------

 The following messages can be dispatched:

 --------------------------------------------------------------------------------------- 
  Symfony\Component\Messenger\Tests\Fixtures\DummyQuery                                  
      handled by Symfony\Component\Messenger\Tests\Fixtures\DummyQueryHandler            
  Symfony\Component\Messenger\Tests\Fixtures\MultipleBusesMessage                        
      handled by Symfony\Component\Messenger\Tests\Fixtures\MultipleBusesMessageHandler  
 --------------------------------------------------------------------------------------- 


TXT
            , $tester->getDisplay(true)
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


TXT
            , $tester->getDisplay(true)
        );
    }

    public function testExceptionOnUnknownBusArgument()
    {
        $this->expectException('Symfony\Component\Console\Exception\RuntimeException');
        $this->expectExceptionMessage('Bus "unknown_bus" does not exist. Known buses are command_bus, query_bus.');
        $command = new DebugCommand(['command_bus' => [], 'query_bus' => []]);

        $tester = new CommandTester($command);
        $tester->execute(['bus' => 'unknown_bus'], ['decorated' => false]);
    }
}
