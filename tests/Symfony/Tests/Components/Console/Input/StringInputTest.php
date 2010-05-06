<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\Console\Input;

use Symfony\Components\Console\Input\StringInput;

class StringInputTest extends \PHPUnit_Framework_TestCase
{
    public function testTokenize()
    {
        $input = new TestInput1('');
        $this->assertEquals(array(), $input->getTokens(), '->tokenize() parses an empty string');

        $input = new TestInput1('foo');
        $this->assertEquals(array('foo'), $input->getTokens(), '->tokenize() parses arguments');

        $input = new TestInput1('  foo  bar  ');
        $this->assertEquals(array('foo', 'bar'), $input->getTokens(), '->tokenize() ignores whitespaces between arguments');

        $input = new TestInput1('"quoted"');
        $this->assertEquals(array('quoted'), $input->getTokens(), '->tokenize() parses quoted arguments');

        $input = new TestInput1("'quoted'");
        $this->assertEquals(array('quoted'), $input->getTokens(), '->tokenize() parses quoted arguments');

        $input = new TestInput1('\"quoted\"');
        $this->assertEquals(array('"quoted"'), $input->getTokens(), '->tokenize() parses escaped-quoted arguments');

        $input = new TestInput1("\'quoted\'");
        $this->assertEquals(array('\'quoted\''), $input->getTokens(), '->tokenize() parses escaped-quoted arguments');

        $input = new TestInput1('-a');
        $this->assertEquals(array('-a'), $input->getTokens(), '->tokenize() parses short options');

        $input = new TestInput1('-azc');
        $this->assertEquals(array('-azc'), $input->getTokens(), '->tokenize() parses aggregated short options');

        $input = new TestInput1('-awithavalue');
        $this->assertEquals(array('-awithavalue'), $input->getTokens(), '->tokenize() parses short options with a value');

        $input = new TestInput1('-a"foo bar"');
        $this->assertEquals(array('-afoo bar'), $input->getTokens(), '->tokenize() parses short options with a value');

        $input = new TestInput1('-a"foo bar""foo bar"');
        $this->assertEquals(array('-afoo barfoo bar'), $input->getTokens(), '->tokenize() parses short options with a value');

        $input = new TestInput1('-a\'foo bar\'');
        $this->assertEquals(array('-afoo bar'), $input->getTokens(), '->tokenize() parses short options with a value');

        $input = new TestInput1('-a\'foo bar\'\'foo bar\'');
        $this->assertEquals(array('-afoo barfoo bar'), $input->getTokens(), '->tokenize() parses short options with a value');

        $input = new TestInput1('-a\'foo bar\'"foo bar"');
        $this->assertEquals(array('-afoo barfoo bar'), $input->getTokens(), '->tokenize() parses short options with a value');

        $input = new TestInput1('--long-option');
        $this->assertEquals(array('--long-option'), $input->getTokens(), '->tokenize() parses long options');

        $input = new TestInput1('--long-option=foo');
        $this->assertEquals(array('--long-option=foo'), $input->getTokens(), '->tokenize() parses long options with a value');

        $input = new TestInput1('--long-option="foo bar"');
        $this->assertEquals(array('--long-option=foo bar'), $input->getTokens(), '->tokenize() parses long options with a value');

        $input = new TestInput1('--long-option="foo bar""another"');
        $this->assertEquals(array('--long-option=foo baranother'), $input->getTokens(), '->tokenize() parses long options with a value');

        $input = new TestInput1('--long-option=\'foo bar\'');
        $this->assertEquals(array('--long-option=foo bar'), $input->getTokens(), '->tokenize() parses long options with a value');

        $input = new TestInput1("--long-option='foo bar''another'");
        $this->assertEquals(array("--long-option=foo baranother"), $input->getTokens(), '->tokenize() parses long options with a value');

        $input = new TestInput1("--long-option='foo bar'\"another\"");
        $this->assertEquals(array("--long-option=foo baranother"), $input->getTokens(), '->tokenize() parses long options with a value');

        $input = new TestInput1('foo -a -ffoo --long bar');
        $this->assertEquals(array('foo', '-a', '-ffoo', '--long', 'bar'), $input->getTokens(), '->tokenize() parses when several arguments and options');
    }
}

class TestInput1 extends StringInput
{
    public function getTokens()
    {
        return $this->tokens;
    }
}
