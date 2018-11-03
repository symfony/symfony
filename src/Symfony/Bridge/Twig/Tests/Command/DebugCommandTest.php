<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Command\DebugCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class DebugCommandTest extends TestCase
{
    public function testDebugCommand()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(array(), array('decorated' => false));

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        $this->assertContains('Functions', trim($tester->getDisplay()));
    }

    public function testFilterAndJsonFormatOptions()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(array('--filter' => 'abs', '--format' => 'json'), array('decorated' => false));

        $expected = array(
            'filters' => array('abs' => array()),
        );

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        $this->assertEquals($expected, json_decode($tester->getDisplay(true), true));
    }

    /**
     * @expectedException \Symfony\Component\Console\Exception\InvalidArgumentException
     * @expectedExceptionMessage Malformed namespaced template name "@foo" (expecting "@namespace/template_name").
     */
    public function testMalformedTemplateName()
    {
        $this->createCommandTester()->execute(array('name' => '@foo'));
    }

    /**
     * @dataProvider getDebugTemplateNameTestData
     */
    public function testDebugTemplateName(array $input, string $output, array $paths)
    {
        $tester = $this->createCommandTester($paths);
        $ret = $tester->execute($input, array('decorated' => false));

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        $this->assertStringMatchesFormat($output, $tester->getDisplay(true));
    }

    public function getDebugTemplateNameTestData()
    {
        $defaultPaths = array(
            'templates/' => null,
            'templates/bundles/TwigBundle/' => 'Twig',
            'vendors/twig-bundle/Resources/views/' => 'Twig',
        );

        yield 'no template paths configured for your application' => array(
            'input' => array('name' => 'base.html.twig'),
            'output' => <<<TXT

Matched File
------------

 Template name "base.html.twig" not found%A

Configured Paths
----------------

 No template paths configured for your application%s

 ----------- -------------------------------------%A
  Namespace   Paths%A
 ----------- -------------------------------------%A
  @Twig       vendors/twig-bundle/Resources/views%e%A
 ----------- -------------------------------------%A


TXT
            ,
            'paths' => array('vendors/twig-bundle/Resources/views/' => 'Twig'),
        );

        yield 'no matched template' => array(
            'input' => array('name' => '@App/foo.html.twig'),
            'output' => <<<TXT

Matched File
------------

 Template name "@App/foo.html.twig" not found%A

Configured Paths
----------------

 No template paths configured for "@App" namespace%A

 ----------- -------------------------------------%A
  Namespace   Paths%A
 ----------- -------------------------------------%A
  (None)      templates%e%A
  %A
  @Twig       templates/bundles/TwigBundle%e%A
              vendors/twig-bundle/Resources/views%e%A 
 ----------- -------------------------------------%A


TXT
            ,
            'paths' => $defaultPaths,
        );

        yield 'matched file' => array(
            'input' => array('name' => 'base.html.twig'),
            'output' => <<<TXT

Matched File
------------

 [OK] templates%ebase.html.twig%A

Configured Paths
----------------

 ----------- ------------%A
  Namespace   Paths%A
 ----------- ------------%A
  (None)      templates%e%A
 ----------- ------------%A


TXT
            ,
            'paths' => $defaultPaths,
        );

        yield 'overridden files' => array(
            'input' => array('name' => '@Twig/error.html.twig'),
            'output' => <<<TXT

Matched File
------------

 [OK] templates%ebundles%eTwigBundle%eerror.html.twig%A

Overridden Files
----------------

 * vendors%etwig-bundle%eResources%eviews%eerror.html.twig

Configured Paths
----------------

 ----------- -------------------------------------- 
  Namespace   Paths%A
 ----------- -------------------------------------- 
  @Twig       templates/bundles/TwigBundle%e%A
              vendors/twig-bundle/Resources/views%e%A
 ----------- -------------------------------------- 


TXT
            ,
            'paths' => $defaultPaths,
        );

        yield 'template namespace alternative' => array(
            'input' => array('name' => '@Twg/error.html.twig'),
            'output' => <<<TXT

Matched File
------------

 Template name "@Twg/error.html.twig" not found%A

Configured Paths
----------------

 No template paths configured for "@Twg" namespace%A
%A
%wDid you mean this?%A
%w@Twig%A


TXT
            ,
            'paths' => $defaultPaths,
        );

        yield 'template name alternative' => array(
            'input' => array('name' => '@Twig/eror.html.twig'),
            'output' => <<<TXT

Matched File
------------

 Template name "@Twig/eror.html.twig" not found%A
%A
%wDid you mean one of these?%A
%w@Twig/base.html.twig%A
%w@Twig/error.html.twig%A

Configured Paths
----------------

 ----------- -------------------------------------- 
  Namespace   Paths                                 
 ----------- -------------------------------------- 
  @Twig       templates/bundles/TwigBundle%e%A
              vendors/twig-bundle/Resources/views%e%A
 ----------- -------------------------------------- 


TXT
            ,
            'paths' => $defaultPaths,
        );
    }

    private function createCommandTester(array $paths = array()): CommandTester
    {
        $projectDir = \dirname(__DIR__).\DIRECTORY_SEPARATOR.'Fixtures';
        $loader = new FilesystemLoader(array(), $projectDir);
        foreach ($paths as $path => $namespace) {
            if (null === $namespace) {
                $loader->addPath($path);
            } else {
                $loader->addPath($path, $namespace);
            }
        }

        $application = new Application();
        $application->add(new DebugCommand(new Environment($loader), $projectDir));
        $command = $application->find('debug:twig');

        return new CommandTester($command);
    }
}
