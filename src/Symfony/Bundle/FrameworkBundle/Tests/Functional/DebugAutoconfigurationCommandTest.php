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

 ----------- ----------------- 
  Option      Value            
 ----------- ----------------- 
  Tags        console.command  
  Public      yes              
  Shared      yes              
  Autowired   no
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

    public function testAutoconfigurationWithMethodCall()
    {
        static::bootKernel(array('test_case' => 'ContainerDebug', 'root_config' => 'config.yml'));

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(array('command' => 'debug:autoconfiguration', 'search' => 'PsrLogLoggerAwareInterface'));

        $this->assertContains('Psr\Log\LoggerAwareInterface', $tester->getDisplay());
        $expectedMethodCallOutput = <<<EOD
Method call   - [setLogger, ['@logger']]
EOD;
        $this->assertContains($expectedMethodCallOutput, $tester->getDisplay());
    }

    public function testAutoconfigurationWithTagsAttributes()
    {
        static::bootKernel(array('test_case' => 'ContainerDebug', 'root_config' => 'config.yml'));

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(array('command' => 'debug:autoconfiguration', 'search' => 'SymfonyContractsServiceResetInterface'));

        $this->assertContains('Symfony\Contracts\Service\ResetInterface', $tester->getDisplay());
        $expectedTagsAttributesOutput = <<<EOD
  Tags attributes   [                          
                      [                        
                        [                      
                          "method" => "reset"  
                        ]                      
                      ]                        
                    ]       
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
