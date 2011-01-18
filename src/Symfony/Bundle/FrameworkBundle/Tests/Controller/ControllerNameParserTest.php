<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerNameParser;
use Symfony\Bundle\FrameworkBundle\Tests\Logger;
use Symfony\Bundle\FrameworkBundle\Tests\Kernel;

require_once __DIR__.'/../Kernel.php';
require_once __DIR__.'/../Logger.php';

class ControllerNameParserTest extends TestCase
{
    public function testParse()
    {
        $kernel = new Kernel();
        $kernel->boot();
        $logger = new Logger();
        $parser = new ControllerNameParser($kernel, $logger);

        $this->assertEquals('TestBundle\FooBundle\Controller\DefaultController::indexAction', $parser->parse('FooBundle:Default:index'), '->parse() converts a short a:b:c notation string to a class::method string');
        $this->assertEquals('TestBundle\FooBundle\Controller\Sub\DefaultController::indexAction', $parser->parse('FooBundle:Sub\Default:index'), '->parse() converts a short a:b:c notation string to a class::method string');
        $this->assertEquals('TestBundle\Fabpot\FooBundle\Controller\DefaultController::indexAction', $parser->parse('SensioFooBundle:Default:index'), '->parse() converts a short a:b:c notation string to a class::method string');
        $this->assertEquals('TestBundle\Sensio\Cms\FooBundle\Controller\DefaultController::indexAction', $parser->parse('SensioCmsFooBundle:Default:index'), '->parse() converts a short a:b:c notation string to a class::method string');

        try {
            $parser->parse('foo:');
            $this->fail('->parse() throws an \InvalidArgumentException if the controller is not an a:b:c string');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->parse() throws an \InvalidArgumentException if the controller is not an a:b:c string');
        }

        try {
            $parser->parse('BarBundle:Default:index');
            $this->fail('->parse() throws a \InvalidArgumentException if the class is found but does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->parse() throws a \LogicException if the class is found but does not exist');
        }
    }
}
