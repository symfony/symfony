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
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\ErrorHandler\Exception\SilencedErrorContext;
use Symfony\Component\VarDumper\Caster\Caster;
use Symfony\Component\VarDumper\Caster\ExceptionCaster;
use Symfony\Component\VarDumper\Caster\FrameStub;
use Symfony\Component\VarDumper\Caster\TraceStub;
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

    private function getTestError($msg): \Error
    {
        return new \Error(''.$msg);
    }

    private function getTestErrorException($msg): \ErrorException
    {
        return new \ErrorException(''.$msg);
    }

    private function getTestSilencedErrorContext(): SilencedErrorContext
    {
        return new SilencedErrorContext(\E_ERROR, __FILE__, __LINE__);
    }

    protected function tearDown(): void
    {
        ExceptionCaster::$srcContext = 1;
        ExceptionCaster::$traceArgs = true;
    }

    public function testDefaultSettings()
    {
        $ref = ['foo'];
        $e = $this->getTestException('foo', $ref);

        $expectedDump = <<<'EODUMP'
Exception {
  #message: "foo"
  #code: 0
  #file: "%sExceptionCasterTest.php"
  #line: %d
  trace: {
    %s%eTests%eCaster%eExceptionCasterTest.php:%d {
      Symfony\Component\VarDumper\Tests\Caster\ExceptionCasterTest->getTestException($msg, &$ref = null)
      › {
      ›     return new \Exception(''.$msg);
      › }
    }
    %s%eTests%eCaster%eExceptionCasterTest.php:%d { …}
%A
EODUMP;

        $this->assertDumpMatchesFormat($expectedDump, $e);
        $this->assertSame(['foo'], $ref);
    }

    public function testDefaultSettingsOnError()
    {
        $e = $this->getTestError('foo');

        $expectedDump = <<<'EODUMP'
Error {
  #message: "foo"
  #code: 0
  #file: "%sExceptionCasterTest.php"
  #line: %d
  trace: {
    %s%eTests%eCaster%eExceptionCasterTest.php:%d {
      Symfony\Component\VarDumper\Tests\Caster\ExceptionCasterTest->getTestError($msg): Error
      › {
      ›     return new \Error(''.$msg);
      › }
    }
    %s%eTests%eCaster%eExceptionCasterTest.php:%d { …}
%A
EODUMP;

        $this->assertDumpMatchesFormat($expectedDump, $e);
    }

    public function testDefaultSettingsOnErrorException()
    {
        $e = $this->getTestErrorException('foo');

        $expectedDump = <<<'EODUMP'
ErrorException {
  #message: "foo"
  #code: 0
  #file: "%sExceptionCasterTest.php"
  #line: %d
  #severity: E_ERROR
  trace: {
    %s%eTests%eCaster%eExceptionCasterTest.php:%d {
      Symfony\Component\VarDumper\Tests\Caster\ExceptionCasterTest->getTestErrorException($msg): ErrorException
      › {
      ›     return new \ErrorException(''.$msg);
      › }
    }
    %s%eTests%eCaster%eExceptionCasterTest.php:%d { …}
%A
EODUMP;

        $this->assertDumpMatchesFormat($expectedDump, $e);
    }

    /**
     * @requires function \Symfony\Component\ErrorHandler\Exception\SilencedErrorContext::__construct
     */
    public function testCastSilencedErrorContext()
    {
        $e = $this->getTestSilencedErrorContext();

        $expectedDump = <<<'EODUMP'
Symfony\Component\ErrorHandler\Exception\SilencedErrorContext {
  +count: 1
  -severity: E_ERROR
  trace: {
    %s%eTests%eCaster%eExceptionCasterTest.php:%d {
      › {
      ›     return new SilencedErrorContext(\E_ERROR, __FILE__, __LINE__);
      › }
    }
  }
}
EODUMP;

        $this->assertDumpMatchesFormat($expectedDump, $e);
    }

    public function testSeek()
    {
        $e = $this->getTestException(2);

        $expectedDump = <<<'EODUMP'
{
  %s%eTests%eCaster%eExceptionCasterTest.php:%d {
    Symfony\Component\VarDumper\Tests\Caster\ExceptionCasterTest->getTestException($msg, &$ref = null)
    › {
    ›     return new \Exception(''.$msg);
    › }
  }
  %s%eTests%eCaster%eExceptionCasterTest.php:%d { …}
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
  #line: %d
  trace: {
    %sExceptionCasterTest.php:%d {
      Symfony\Component\VarDumper\Tests\Caster\ExceptionCasterTest->getTestException($msg, &$ref = null)
      › {
      ›     return new \Exception(''.$msg);
      › }
    }
    %s%eTests%eCaster%eExceptionCasterTest.php:%d { …}
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
  #line: %d
  trace: {
    %s%eTests%eCaster%eExceptionCasterTest.php:%d
    %s%eTests%eCaster%eExceptionCasterTest.php:%d
%A
EODUMP;

        $this->assertDumpMatchesFormat($expectedDump, $e);
    }

    public function testShouldReturnTraceForConcreteTwigWithError()
    {
        require_once \dirname(__DIR__).'/Fixtures/Twig.php';

        $innerExc = (new \__TwigTemplate_VarDumperFixture_u75a09(null, __FILE__))->provideError();
        $nestingWrapper = new \stdClass();
        $nestingWrapper->trace = new TraceStub($innerExc->getTrace());

        $expectedDump = <<<'EODUMP'
{
  +"trace": {
    %sTwig.php:%d {
      AbstractTwigTemplate->provideError()
      › {
      ›     return $this->createError();
      › }
    }
    %sExceptionCasterTest.php:%d { …}
%A
EODUMP;

        $this->assertDumpMatchesFormat($expectedDump, $nestingWrapper);
    }

    public function testHtmlDump()
    {
        if (\ini_get('xdebug.file_link_format') || get_cfg_var('xdebug.file_link_format')) {
            $this->markTestSkipped('A custom file_link_format is defined.');
        }

        $e = $this->getTestException(1);
        ExceptionCaster::$srcContext = -1;

        $cloner = new VarCloner();
        $cloner->setMaxItems(1);
        $dumper = new HtmlDumper();
        $dumper->setDumpHeader('<foo></foo>');
        $dumper->setDumpBoundaries('<bar>', '</bar>');
        $dump = $dumper->dump($cloner->cloneVar($e)->withRefHandles(false), true);

        $expectedDump = <<<'EODUMP'
<foo></foo><bar><span class=sf-dump-note>Exception</span> {<samp data-depth=1 class=sf-dump-expanded>
  #<span class=sf-dump-protected title="Protected property">message</span>: "<span class=sf-dump-str>1</span>"
  #<span class=sf-dump-protected title="Protected property">code</span>: <span class=sf-dump-num>0</span>
  #<span class=sf-dump-protected title="Protected property">file</span>: "<span class="sf-dump-str sf-dump-ellipsization" title="%sExceptionCasterTest.php
%d characters"><span class="sf-dump-ellipsis sf-dump-ellipsis-path">%s%eVarDumper</span><span class="sf-dump-ellipsis sf-dump-ellipsis-path">%e</span><span class="sf-dump-ellipsis-tail">Tests%eCaster%eExceptionCasterTest.php</span></span>"
  #<span class=sf-dump-protected title="Protected property">line</span>: <span class=sf-dump-num>%d</span>
  <span class=sf-dump-meta>trace</span>: {<samp data-depth=2 class=sf-dump-compact>
    <span class="sf-dump-meta sf-dump-ellipsization" title="%sExceptionCasterTest.php
Stack level %d."><span class="sf-dump-ellipsis sf-dump-ellipsis-path">%s%eVarDumper</span><span class="sf-dump-ellipsis sf-dump-ellipsis-path">%e</span><span class="sf-dump-ellipsis-tail">Tests%eCaster%eExceptionCasterTest.php</span></span>:<span class=sf-dump-num>%d</span>
     &#8230;%d
  </samp>}
</samp>}
</bar>
EODUMP;

        $this->assertStringMatchesFormat($expectedDump, $dump);
    }

    public function testFrameWithTwig()
    {
        require_once \dirname(__DIR__).'/Fixtures/Twig.php';

        $f = [
            new FrameStub([
                'file' => \dirname(__DIR__).'/Fixtures/Twig.php',
                'line' => 33,
                'class' => '__TwigTemplate_VarDumperFixture_u75a09',
            ]),
            new FrameStub([
                'file' => \dirname(__DIR__).'/Fixtures/Twig.php',
                'line' => 34,
                'class' => '__TwigTemplate_VarDumperFixture_u75a09',
                'object' => new \__TwigTemplate_VarDumperFixture_u75a09(null, __FILE__),
            ]),
        ];

        $expectedDump = <<<'EODUMP'
array:2 [
  0 => {
    class: "__TwigTemplate_VarDumperFixture_u75a09"
    src: {
      %sTwig.php:1 {
        ›%s
        › foo bar
        ›   twig source
      }
    }
  }
  1 => {
    class: "__TwigTemplate_VarDumperFixture_u75a09"
    object: __TwigTemplate_VarDumperFixture_u75a09 {
    %A
    }
    src: {
      %sExceptionCasterTest.php:2 {
        › foo bar
        ›   twig source
        ›%s
      }
    }
  }
]
EODUMP;

        $this->assertDumpMatchesFormat($expectedDump, $f);
    }

    public function testExcludeVerbosity()
    {
        $e = $this->getTestException('foo');

        $expectedDump = <<<'EODUMP'
Exception {
  #message: "foo"
  #code: 0
  #file: "%sExceptionCasterTest.php"
  #line: %d
}
EODUMP;

        $this->assertDumpMatchesFormat($expectedDump, $e, Caster::EXCLUDE_VERBOSE);
    }

    public function testAnonymous()
    {
        $e = new \Exception(\sprintf('Boo "%s" ba.', (new class('Foo') extends \Exception {
        })::class));

        $expectedDump = <<<'EODUMP'
Exception {
  #message: "Boo "Exception@anonymous" ba."
  #code: 0
  #file: "%sExceptionCasterTest.php"
  #line: %d
}
EODUMP;

        $this->assertDumpMatchesFormat($expectedDump, $e, Caster::EXCLUDE_VERBOSE);
    }

    /**
     * @requires function \Symfony\Component\ErrorHandler\Exception\FlattenException::create
     */
    public function testFlattenException()
    {
        $f = FlattenException::createFromThrowable(new \Exception('Hello'));

        $expectedDump = <<<'EODUMP'
array:1 [
  0 => Symfony\Component\ErrorHandler\Exception\FlattenException {
    -message: "Hello"
    -code: 0
    -previous: null
    -trace: array:%d %a
    -traceAsString: ""…%d
    -class: "Exception"
    -statusCode: 500
    -statusText: "Internal Server Error"
    -headers: []
    -file: "%sExceptionCasterTest.php"
    -line: %d
    -asString: null
    -dataRepresentation: ? Symfony\Component\VarDumper\Cloner\Data
  }
]
EODUMP;

        $this->assertDumpMatchesFormat($expectedDump, [$f], Caster::EXCLUDE_VERBOSE);
    }
}
