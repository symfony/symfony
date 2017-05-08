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
use Symfony\Component\VarDumper\Caster\ArgsStub;
use Symfony\Component\VarDumper\Caster\ClassStub;
use Symfony\Component\VarDumper\Caster\ConstStub;
use Symfony\Component\VarDumper\Caster\LinkStub;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;
use Symfony\Component\VarDumper\Tests\Fixtures\FooInterface;

class StubCasterTest extends TestCase
{
    use VarDumperTestTrait;

    public function testArgsStubWithDefaults($foo = 234, $bar = 456)
    {
        $args = array(new ArgsStub(array(123), __FUNCTION__, __CLASS__));

        $expectedDump = <<<'EODUMP'
array:1 [
  0 => {
    $foo: 123
  }
]
EODUMP;

        $this->assertDumpMatchesFormat($expectedDump, $args);
    }

    public function testArgsStubWithExtraArgs($foo = 234)
    {
        $args = array(new ArgsStub(array(123, 456), __FUNCTION__, __CLASS__));

        $expectedDump = <<<'EODUMP'
array:1 [
  0 => {
    $foo: 123
    ...: {
      456
    }
  }
]
EODUMP;

        $this->assertDumpMatchesFormat($expectedDump, $args);
    }

    public function testArgsStubNoParamWithExtraArgs()
    {
        $args = array(new ArgsStub(array(123), __FUNCTION__, __CLASS__));

        $expectedDump = <<<'EODUMP'
array:1 [
  0 => {
    123
  }
]
EODUMP;

        $this->assertDumpMatchesFormat($expectedDump, $args);
    }

    public function testArgsStubWithClosure()
    {
        $args = array(new ArgsStub(array(123), '{closure}', null));

        $expectedDump = <<<'EODUMP'
array:1 [
  0 => {
    123
  }
]
EODUMP;

        $this->assertDumpMatchesFormat($expectedDump, $args);
    }

    public function testLinkStub()
    {
        $var = array(new LinkStub(__CLASS__, 0, __FILE__));

        $cloner = new VarCloner();
        $dumper = new HtmlDumper();
        $dumper->setDumpHeader('<foo></foo>');
        $dumper->setDumpBoundaries('<bar>', '</bar>');
        $dumper->setDisplayOptions(array('fileLinkFormat' => '%f:%l'));
        $dump = $dumper->dump($cloner->cloneVar($var), true);

        $expectedDump = <<<'EODUMP'
<foo></foo><bar><span class=sf-dump-note>array:1</span> [<samp>
  <span class=sf-dump-index>0</span> => "<a href="%sStubCasterTest.php:0" target="_blank" rel="noopener noreferrer"><span class=sf-dump-str title="55 characters">Symfony\Component\VarDumper\Tests\Caster\StubCasterTest</span></a>"
</samp>]
</bar>
EODUMP;

        $this->assertStringMatchesFormat($expectedDump, $dump);
    }

    public function testClassStub()
    {
        $var = array(new ClassStub('hello', array(FooInterface::class, 'foo')));

        $cloner = new VarCloner();
        $dumper = new HtmlDumper();
        $dumper->setDumpHeader('<foo></foo>');
        $dumper->setDumpBoundaries('<bar>', '</bar>');
        $dump = $dumper->dump($cloner->cloneVar($var), true, array('fileLinkFormat' => '%f:%l'));

        $expectedDump = <<<'EODUMP'
<foo></foo><bar><span class=sf-dump-note>array:1</span> [<samp>
  <span class=sf-dump-index>0</span> => "<a href="%sFooInterface.php:10" target="_blank" rel="noopener noreferrer"><span class=sf-dump-str title="5 characters">hello</span></a>"
</samp>]
</bar>
EODUMP;

        $this->assertStringMatchesFormat($expectedDump, $dump);
    }

    public function testClassStubWithNotExistingClass()
    {
        $var = array(new ClassStub(NotExisting::class));

        $cloner = new VarCloner();
        $dumper = new HtmlDumper();
        $dumper->setDumpHeader('<foo></foo>');
        $dumper->setDumpBoundaries('<bar>', '</bar>');
        $dump = $dumper->dump($cloner->cloneVar($var), true);

        $expectedDump = <<<'EODUMP'
<foo></foo><bar><span class=sf-dump-note>array:1</span> [<samp>
  <span class=sf-dump-index>0</span> => "<span class=sf-dump-str title="Symfony\Component\VarDumper\Tests\Caster\NotExisting
52 characters"><span class="sf-dump-ellipsis sf-dump-ellipsis-class">Symfony\Component\VarDumper\Tests\Caster</span><span class=sf-dump-ellipsis>\</span>NotExisting</span>"
</samp>]
</bar>
EODUMP;

        $this->assertStringMatchesFormat($expectedDump, $dump);
    }

    public function testClassStubWithNotExistingMethod()
    {
        $var = array(new ClassStub('hello', array(FooInterface::class, 'missing')));

        $cloner = new VarCloner();
        $dumper = new HtmlDumper();
        $dumper->setDumpHeader('<foo></foo>');
        $dumper->setDumpBoundaries('<bar>', '</bar>');
        $dump = $dumper->dump($cloner->cloneVar($var), true, array('fileLinkFormat' => '%f:%l'));

        $expectedDump = <<<'EODUMP'
<foo></foo><bar><span class=sf-dump-note>array:1</span> [<samp>
  <span class=sf-dump-index>0</span> => "<a href="%sFooInterface.php:5" target="_blank" rel="noopener noreferrer"><span class=sf-dump-str title="5 characters">hello</span></a>"
</samp>]
</bar>
EODUMP;

        $this->assertStringMatchesFormat($expectedDump, $dump);
    }

    /**
     * @dataProvider provideFlags
     */
    public function testContStubFromFlag($value, $prefix, $expected)
    {
        $var = array(ConstStub::fromFlag($value, $prefix));

        $expectedDump = <<<EODUMP
array:1 [
  0 => $expected
]
EODUMP;

        $this->assertDumpMatchesFormat($expectedDump, $var);
    }

    public function provideFlags()
    {
        return array(
            array(0, 'E_', '0'),
            array(E_ERROR, 'E_', 'E_ERROR'),
            array(E_ERROR | E_WARNING, 'E_', 'E_ERROR | E_WARNING'),
            array(E_ERROR | E_WARNING | E_NOTICE, 'E_', 'E_ERROR | E_WARNING | E_NOTICE'),
            array(E_ALL, 'E_', 'E_ERROR | E_WARNING | E_PARSE | E_NOTICE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_COMPILE_WARNING | E_USER_ERROR | E_USER_WARNING | E_USER_NOTICE | E_STRICT | E_RECOVERABLE_ERROR | E_DEPRECATED | E_USER_DEPRECATED'),
            array(E_DEPRECATED | E_USER_DEPRECATED | E_USER_DEPRECATED * 2 | E_USER_DEPRECATED * 4, 'E_', 'E_DEPRECATED | E_USER_DEPRECATED | 32768 | 65536'),
        );
    }
}
