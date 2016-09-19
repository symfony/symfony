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

use Symfony\Component\VarDumper\Caster\ExceptionCaster;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

class ExceptionCasterTest extends \PHPUnit_Framework_TestCase
{
    use VarDumperTestTrait;

    private function getTestException()
    {
        return new \Exception('foo');
    }

    protected function tearDown()
    {
        ExceptionCaster::$srcContext = 1;
        ExceptionCaster::$traceArgs = true;
    }

    public function testDefaultSettings()
    {
        $e = $this->getTestException($this);

        $expectedDump = <<<'EODUMP'
Exception {
  #message: "foo"
  #code: 0
  #file: "%sExceptionCasterTest.php"
  #line: 25
  -trace: {
    %sExceptionCasterTest.php:25: {
      : {
      :     return new \Exception('foo');
      : }
    }
    %sExceptionCasterTest.php:%d: {
      : {
      :     $e = $this->getTestException($this);
      : 
      arguments: {
        Symfony\Component\VarDumper\Tests\Caster\ExceptionCasterTest {#1 …}
      }
    }
%A
EODUMP;

        $this->assertDumpMatchesFormat($expectedDump, $e);
    }

    public function testSeek()
    {
        $e = $this->getTestException(2);

        $expectedDump = <<<'EODUMP'
{
  %sExceptionCasterTest.php:25: {
    : {
    :     return new \Exception('foo');
    : }
  }
  %sExceptionCasterTest.php:%d: {
    : {
    :     $e = $this->getTestException(2);
    : 
    arguments: {
      2
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
  #message: "foo"
  #code: 0
  #file: "%sExceptionCasterTest.php"
  #line: 25
  -trace: {
    %sExceptionCasterTest.php:25: {
      : {
      :     return new \Exception('foo');
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
  #message: "foo"
  #code: 0
  #file: "%sExceptionCasterTest.php"
  #line: 25
  -trace: {
    %sExceptionCasterTest.php: 25
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
  #<span class=sf-dump-protected title="Protected property">message</span>: "<span class=sf-dump-str title="3 characters">foo</span>"
  #<span class=sf-dump-protected title="Protected property">code</span>: <span class=sf-dump-num>0</span>
  #<span class=sf-dump-protected title="Protected property">file</span>: "<span class=sf-dump-str title="%sExceptionCasterTest.php
%d characters"><span class=sf-dump-ellipsis>%sTests</span>%eCaster%eExceptionCasterTest.php</span>"
  #<span class=sf-dump-protected title="Protected property">line</span>: <span class=sf-dump-num>25</span>
  -<span class=sf-dump-private title="Private property defined in class:&#10;`Exception`">trace</span>: {<samp>
    <span class=sf-dump-meta title="%sExceptionCasterTest.php
Stack level %d."><span class=sf-dump-ellipsis>%sVarDumper%eTests</span>%eCaster%eExceptionCasterTest.php</span>: <span class=sf-dump-num>25</span>
     &hellip;12
  </samp>}
</samp>}
</bar>
EODUMP;

        $this->assertStringMatchesFormat($expectedDump, $dump);
    }
}
