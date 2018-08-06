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
    protected function setUp()
    {
        putenv('COLUMNS='.(119 + \strlen(PHP_EOL)));
    }

    protected function tearDown()
    {
        putenv('COLUMNS=');
    }

    public function testOutput()
    {
        $command = new DebugCommand(
            array(
                'command_bus' => array(
                    DummyCommand::class => array(DummyCommandHandler::class),
                    MultipleBusesMessage::class => array(MultipleBusesMessageHandler::class),
                ),
                'query_bus' => array(
                    DummyQuery::class => array(DummyQueryHandler::class),
                    MultipleBusesMessage::class => array(MultipleBusesMessageHandler::class),
                ),
            )
        );

        $tester = new CommandTester($command);
        $tester->execute(array(), array('decorated' => false));

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

        $tester->execute(array('bus' => 'query_bus'), array('decorated' => false));

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
        $command = new DebugCommand(array('command_bus' => array(), 'query_bus' => array()));

        $tester = new CommandTester($command);
        $tester->execute(array(), array('decorated' => false));

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

    /**
     * @expectedException \Symfony\Component\Console\Exception\RuntimeException
     * @expectedExceptionMessage Bus "unknown_bus" does not exist. Known buses are command_bus, query_bus.
     */
    public function testExceptionOnUnknownBusArgument()
    {
        $command = new DebugCommand(array('command_bus' => array(), 'query_bus' => array()));

        $tester = new CommandTester($command);
        $tester->execute(array('bus' => 'unknown_bus'), array('decorated' => false));
    }
}
