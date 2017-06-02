<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Caster;

use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Caster\ExceptionCaster;
use Symfony\Component\VarDumper\Caster\FrameStub;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

class ExceptionCasterTest extends TestCase
{
    use VarDumperTestTrait;

    private function getTestException($msg, &$ref = null)
    {
        return new \Exception(''.$msg);
    }

    protected function tearDown()
    {
        ExceptionCaster::$srcContext = 1;
        ExceptionCaster::$traceArgs = true;
    }

    public function testDefaultSettings()
    {
        $ref = array('foo');
        $e = $this->getTestException('foo', $ref);

        $expectedDump = <<<'EODUMP'
Exception {
  #message: "foo"
  #code: 0
  #file: "%sExceptionCasterTest.php"
  #line: 27
  trace: {
    %sExceptionCasterTest.php:27: {
      : {
      :     return new \Exception(''.$msg);
      : }
    }
    %sExceptionCasterTest.php:%d: {
      : $ref = array('foo');
      : $e = $this->getTestException('foo', $ref);
      : 
      arguments: {
        $msg: "foo"
        &$ref: array:1 [ …1]
      }
    }
%A
EODUMP;

        $this->assertDumpMatchesFormat($expectedDump, $e);
        $this->assertSame(array('foo'), $ref);
    }

    public function testSeek()
    {
        $e = $this->getTestException(2);

        $expectedDump = <<<'EODUMP'
{
  %sExceptionCasterTest.php:27: {
    : {
    :     return new \Exception(''.$msg);
    : }
  }
  %sExceptionCasterTest.php:%d: {
    : {
    :     $e = $this->getTestException(2);
    : 
    arguments: {
      $msg: 2
    }
  }
%A
EODUMP;

        $this->assertStringMatchesFormat($expectedDump, $this->getDump($e, 'trace'));
    }

    public function testNoArgs()
    {
        $e = $this->getTestException(1);
        ExceptionCaster::$traceArgs = false;

        $expectedDump = <<<'EODUMP'
Exception {
  #message: "1"
  #code: 0
  #file: "%sExceptionCasterTest.php"
  #line: 27
  trace: {
    %sExceptionCasterTest.php:27: {
      : {
      :     return new \Exception(''.$msg);
      : }
    }
    %sExceptionCasterTest.php:%d: {
      : {
      :     $e = $this->getTestException(1);
      :     ExceptionCaster::$traceArgs = false;
    }
%A
EODUMP;

        $this->assertDumpMatchesFormat($expectedDump, $e);
    }

    public function testNoSrcContext()
    {
        $e = $this->getTestException(1);
        ExceptionCaster::$srcContext = -1;

        $expectedDump = <<<'EODUMP'
Exception {
  #message: "1"
  #code: 0
  #file: "%sExceptionCasterTest.php"
  #line: 27
  trace: {
    %sExceptionCasterTest.php: 27
    %sExceptionCasterTest.php: %d
%A
EODUMP;

        $this->assertDumpMatchesFormat($expectedDump, $e);
    }

    public function testHtmlDump()
    {
        $e = $this->getTestException(1);
        ExceptionCaster::$srcContext = -1;

        $cloner = new VarCloner();
        $cloner->setMaxItems(1);
        $dumper = new HtmlDumper();
        $dumper->setDumpHeader('<foo></foo>');
        $dumper->setDumpBoundaries('<bar>', '</bar>');
        $dump = $dumper->dump($cloner->cloneVar($e)->withRefHandles(false), true);

        $expectedDump = <<<'EODUMP'
<foo></foo><bar><span class=sf-dump-note>Exception</span> {<samp>
  #<span class=sf-dump-protected title="Protected property">message</span>: "<span class=sf-dump-str>1</span>"
  #<span class=sf-dump-protected title="Protected property">code</span>: <span class=sf-dump-num>0</span>
  #<span class=sf-dump-protected title="Protected property">file</span>: "<span class=sf-dump-str title="%sExceptionCasterTest.php
%d characters"><span class="sf-dump-ellipsis sf-dump-ellipsis-path">%s%eVarDumper</span><span class=sf-dump-ellipsis>%e</span>Tests%eCaster%eExceptionCasterTest.php</span>"
  #<span class=sf-dump-protected title="Protected property">line</span>: <span class=sf-dump-num>27</span>
  <span class=sf-dump-meta>trace</span>: {<samp>
    <span class=sf-dump-meta title="%sExceptionCasterTest.php
Stack level %d."><span class="sf-dump-ellipsis sf-dump-ellipsis-path">%s%eVarDumper</span><span class=sf-dump-ellipsis>%e</span>Tests%eCaster%eExceptionCasterTest.php</span>: <span class=sf-dump-num>27</span>
     &hellip;%d
  </samp>}
</samp>}
</bar>
EODUMP;

        $this->assertStringMatchesFormat($expectedDump, $dump);
    }

    /**
     * @requires function Twig\Template::getSourceContext
     */
    public function testFrameWithTwig()
    {
        require_once dirname(__DIR__).'/Fixtures/Twig.php';

        $f = array(
            new FrameStub(array(
                'file' => dirname(__DIR__).'/Fixtures/Twig.php',
                'line' => 20,
                'class' => '__TwigTemplate_VarDumperFixture_u75a09',
            )),
            new FrameStub(array(
                'file' => dirname(__DIR__).'/Fixtures/Twig.php',
                'line' => 21,
                'class' => '__TwigTemplate_VarDumperFixture_u75a09',
                'object' => new \__TwigTemplate_VarDumperFixture_u75a09(null, __FILE__),
            )),
        );

        $expectedDump = <<<'EODUMP'
array:2 [
  0 => {
    class: "__TwigTemplate_VarDumperFixture_u75a09"
    src: {
      %sTwig.php:1: {
        : 
        : foo bar
        :   twig source
      }
    }
  }
  1 => {
    class: "__TwigTemplate_VarDumperFixture_u75a09"
    object: __TwigTemplate_VarDumperFixture_u75a09 {
    %A
    }
    src: {
      %sExceptionCasterTest.php:2: {
        : foo bar
        :   twig source
        : 
      }
    }
  }
]

EODUMP;

        $this->assertDumpMatchesFormat($expectedDump, $f);
    }
}
