<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorRenderer\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\ErrorRenderer\Command\DebugCommand;
use Symfony\Component\ErrorRenderer\ErrorRenderer\JsonErrorRenderer;
use Symfony\Component\ErrorRenderer\ErrorRenderer\TxtErrorRenderer;
use Symfony\Component\ErrorRenderer\ErrorRenderer\XmlErrorRenderer;

class DebugCommandTest extends TestCase
{
    public function testAvailableRenderers()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute([], ['decorated' => false]);

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        $this->assertSame(<<<TXT

Error Renderers
===============

 The following error renderers are available:

 -------- ----------------------------------------------------------------- 
  Format   Class                                                            
 -------- ----------------------------------------------------------------- 
  json     Symfony\Component\ErrorRenderer\ErrorRenderer\JsonErrorRenderer  
  xml      Symfony\Component\ErrorRenderer\ErrorRenderer\XmlErrorRenderer   
  txt      Symfony\Component\ErrorRenderer\ErrorRenderer\TxtErrorRenderer   
 -------- ----------------------------------------------------------------- 


TXT
            , $tester->getDisplay(true));
    }

    public function testFormatArgument()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(['format' => 'json'], ['decorated' => false]);

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        $this->assertSame(<<<TXT
{
    "title": "Internal Server Error",
    "status": 500,
    "detail": "Whoops, looks like something went wrong."
}

TXT
            , $tester->getDisplay(true));
    }

    private function createCommandTester()
    {
        $command = new DebugCommand([
            'json' => new JsonErrorRenderer(false),
            'xml' => new XmlErrorRenderer(false),
            'txt' => new TxtErrorRenderer(false),
        ]);

        $application = new Application();
        $application->add($command);

        return new CommandTester($application->find('debug:error-renderer'));
    }

    public function testInvalidFormat()
    {
        $this->expectException('Symfony\Component\Console\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('No error renderer found for format "foo". Known format are json, xml, txt.');
        $tester = $this->createCommandTester();
        $tester->execute(['format' => 'foo'], ['decorated' => false]);
    }
}
