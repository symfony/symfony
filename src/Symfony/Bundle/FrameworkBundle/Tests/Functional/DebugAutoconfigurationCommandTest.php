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
class DebugAutoconfigurationCommandTest extends WebTestCase
{
    public function testBasicFunctionality()
    {
        static::bootKernel(array('test_case' => 'ContainerDebug', 'root_config' => 'config.yml'));

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(array('command' => 'debug:autoconfiguration'));

        $expectedOutput = <<<EOD
Autoconfiguration for "Symfony\Component\Console\Command\Command"
==============================================

 -------- ----------------- 
  Option   Value            
 -------- ----------------- 
  Tag      console.command  
 -------- -----------------
EOD;
        $this->assertContains($expectedOutput, $tester->getDisplay());
    }

    public function testSearchArgument()
    {
        static::bootKernel(array('test_case' => 'ContainerDebug', 'root_config' => 'config.yml'));

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(array('command' => 'debug:autoconfiguration', 'search' => 'logger'));

        $this->assertContains('Psr\Log\LoggerAwareInterface', $tester->getDisplay());
        $this->assertNotContains('Sensio\Bundle\FrameworkExtraBundle', $tester->getDisplay());
    }

    public function testAutoconfigurationWithMethodCalls()
    {
        static::bootKernel(array('test_case' => 'DebugAutoconfiguration', 'root_config' => 'config.yml'));

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(array('command' => 'debug:autoconfiguration', 'search' => 'MethodCalls'));

        $this->assertContains('Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\DebugAutoconfigurationBundle\Autoconfiguration\MethodCalls', $tester->getDisplay());
        $expectedMethodCallOutput = <<<EOD
  Method call   - [setMethodOne, ['@logger']]             
                - [setMethodTwo, [[paramOne, paramOne]]]
EOD;
        $this->assertContains($expectedMethodCallOutput, $tester->getDisplay());
    }

    public function testAutoconfigurationWithMultipleTagsAttributes()
    {
        static::bootKernel(array('test_case' => 'DebugAutoconfiguration', 'root_config' => 'config.yml'));

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(array('command' => 'debug:autoconfiguration', 'search' => 'TagsAttributes'));

        $this->assertContains('Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\DebugAutoconfigurationBundle\Autoconfiguration\TagsAttributes', $tester->getDisplay());
        $expectedTagsAttributesOutput = <<<EOD
  Tag             debugautoconfiguration.tag1  
  Tag attribute   [                            
                    [                          
                      "method" => "debug"      
                    ]                          
                  ]                            
                                               
  Tag             debugautoconfiguration.tag2  
  Tag attribute   [                            
                    [                          
                      "test"                   
                    ]                          
                  ]                            
EOD;
        $this->assertContains($expectedTagsAttributesOutput, $tester->getDisplay());
    }

    public function testAutoconfigurationWithBindings()
    {
        static::bootKernel(array('test_case' => 'DebugAutoconfiguration', 'root_config' => 'config.yml'));

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(array('command' => 'debug:autoconfiguration', 'search' => 'Bindings'));

        $this->assertContains('Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\DebugAutoconfigurationBundle\Autoconfiguration\Bindings', $tester->getDisplay());
        $expectedTagsAttributesOutput = <<<'EOD'
  Bindings   $paramOne: '@logger'       
             $paramTwo: 'binding test'  

EOD;
        $this->assertContains($expectedTagsAttributesOutput, $tester->getDisplay());
    }

    public function testSearchIgnoreBackslashWhenFindingInterfaceOrClass()
    {
        static::bootKernel(array('test_case' => 'ContainerDebug', 'root_config' => 'config.yml'));

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(array('command' => 'debug:autoconfiguration', 'search' => 'PsrLogLoggerAwareInterface'));
        $this->assertContains('Psr\Log\LoggerAwareInterface', $tester->getDisplay());
    }

    public function testSearchNoResults()
    {
        static::bootKernel(array('test_case' => 'ContainerDebug', 'root_config' => 'config.yml'));

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(array('command' => 'debug:autoconfiguration', 'search' => 'foo_fake'), array('capture_stderr_separately' => true));

        $this->assertContains('No autoconfiguration interface/class found matching "foo_fake"', $tester->getErrorOutput());
        $this->assertEquals(1, $tester->getStatusCode());
    }
}
