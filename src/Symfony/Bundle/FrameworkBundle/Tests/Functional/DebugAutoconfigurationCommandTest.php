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

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;

/**
 * @group functional
 */
class DebugAutoconfigurationCommandTest extends AbstractWebTestCase
{
    public function testBasicFunctionality()
    {
        static::bootKernel(['test_case' => 'ContainerDebug', 'root_config' => 'config.yml']);

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'debug:autoconfiguration']);

        $expectedOutput = <<<EOD
Autoconfiguration for "Symfony\Component\Console\Command\Command"
==============================================

 -------- ----------------- 
  Option   Value            
 -------- ----------------- 
  Tag      console.command  
 -------- -----------------
EOD;
        $this->assertStringContainsString($expectedOutput, $tester->getDisplay(true));
    }

    public function testSearchArgument()
    {
        static::bootKernel(['test_case' => 'ContainerDebug', 'root_config' => 'config.yml']);

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'debug:autoconfiguration', 'search' => 'logger']);

        $this->assertStringContainsString('Psr\Log\LoggerAwareInterface', $tester->getDisplay(true));
        $this->assertStringNotContainsString('Sensio\Bundle\FrameworkExtraBundle', $tester->getDisplay(true));
    }

    public function testAutoconfigurationWithMethodCalls()
    {
        static::bootKernel(['test_case' => 'DebugAutoconfiguration', 'root_config' => 'config.yml']);

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'debug:autoconfiguration', 'search' => 'MethodCalls']);

        $this->assertStringContainsString('Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\DebugAutoconfigurationBundle\Autoconfiguration\MethodCalls', $tester->getDisplay(true));
        $expectedMethodCallOutput = <<<EOD
  Method call   - [setMethodOne, ['@logger']]             
                - [setMethodTwo, [[paramOne, paramOne]]]  
EOD;
        $this->assertStringContainsString($expectedMethodCallOutput, $tester->getDisplay(true));
    }

    public function testAutoconfigurationWithMultipleTagsAttributes()
    {
        static::bootKernel(['test_case' => 'DebugAutoconfiguration', 'root_config' => 'config.yml']);

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'debug:autoconfiguration', 'search' => 'TagsAttributes']);

        $this->assertStringContainsString('Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\DebugAutoconfigurationBundle\Autoconfiguration\TagsAttributes', $tester->getDisplay(true));
        $expectedTagsAttributesOutput = <<<EOD
  Tag             debugautoconfiguration.tag1  
  Tag attribute   [                            
                    "method" => "debug"        
                  ]                            
                                               
  Tag             debugautoconfiguration.tag2  
  Tag attribute   [                            
                    "test"                     
                  ]                            
EOD;
        $this->assertStringContainsString($expectedTagsAttributesOutput, $tester->getDisplay(true));
    }

    public function testAutoconfigurationWithBindings()
    {
        static::bootKernel(['test_case' => 'DebugAutoconfiguration', 'root_config' => 'config.yml']);

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'debug:autoconfiguration', 'search' => 'Bindings']);

        $this->assertStringContainsString('Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\DebugAutoconfigurationBundle\Autoconfiguration\Bindings', $tester->getDisplay(true));
        $expectedTagsAttributesOutput = <<<'EOD'
  Bindings   $paramOne: '@logger'       
             $paramTwo: 'binding test'
EOD;
        $this->assertStringContainsString($expectedTagsAttributesOutput, $tester->getDisplay(true));
    }

    public function testSearchIgnoreBackslashWhenFindingInterfaceOrClass()
    {
        static::bootKernel(['test_case' => 'ContainerDebug', 'root_config' => 'config.yml']);

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'debug:autoconfiguration', 'search' => 'PsrLogLoggerAwareInterface']);
        $this->assertStringContainsString('Psr\Log\LoggerAwareInterface', $tester->getDisplay(true));
    }

    public function testSearchNoResults()
    {
        static::bootKernel(['test_case' => 'ContainerDebug', 'root_config' => 'config.yml']);

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'debug:autoconfiguration', 'search' => 'foo_fake'], ['capture_stderr_separately' => true]);

        $this->assertStringContainsString('No autoconfiguration interface/class found matching "foo_fake"', $tester->getErrorOutput());
        $this->assertSame(1, $tester->getStatusCode());
    }
}
