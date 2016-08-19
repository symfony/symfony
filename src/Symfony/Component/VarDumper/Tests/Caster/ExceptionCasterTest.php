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
        $e = $this->getTestException(1);

        $expectedDump = <<<'EODUMP'
Exception {
  #message: "foo"
  #code: 0
  #file: "%sExceptionCasterTest.php"
  #line: 23
  -trace: {
    %d. %sExceptionCasterTest.php:23: {
      22: {
      23:     return new \Exception('foo');
      24: }
    }
    %d. %sExceptionCasterTest.php:%d: {
      %d: {
      %d:     $e = $this->getTestException(1);
      %d: 
      args: {
        1
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
  %d. %sExceptionCasterTest.php:23: {
    22: {
    23:     return new \Exception('foo');
    24: }
  }
  %d. %sExceptionCasterTest.php:%d: {
    %d: {
    %d:     $e = $this->getTestException(2);
    %d: 
    args: {
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
  #line: 23
  -trace: {
    %d. %sExceptionCasterTest.php:23: {
      22: {
      23:     return new \Exception('foo');
      24: }
    }
    %d. %sExceptionCasterTest.php:%d: {
      %d: {
      %d:     $e = $this->getTestException(1);
      %d:     ExceptionCaster::$traceArgs = false;
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
  #line: 23
  -trace: {
    %d. %sExceptionCasterTest.php: 23
    %d. %sExceptionCasterTest.php: %d
%A
EODUMP;

        $this->assertDumpMatchesFormat($expectedDump, $e);
    }
}
