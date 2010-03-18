<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\Console\Input;

require_once __DIR__.'/../../../bootstrap.php';

use Symfony\Components\Console\Input\StringInput;

class StringInputTest extends \PHPUnit_Framework_TestCase
{
  public function testTokenize()
  {
    $input = new TestInput1('');
    $this->assertEquals($input->getTokens(), array(), '->tokenize() parses an empty string');

    $input = new TestInput1('foo');
    $this->assertEquals($input->getTokens(), array('foo'), '->tokenize() parses arguments');

    $input = new TestInput1('  foo  bar  ');
    $this->assertEquals($input->getTokens(), array('foo', 'bar'), '->tokenize() ignores whitespaces between arguments');

    $input = new TestInput1('"quoted"');
    $this->assertEquals($input->getTokens(), array('quoted'), '->tokenize() parses quoted arguments');

    $input = new TestInput1("'quoted'");
    $this->assertEquals($input->getTokens(), array('quoted'), '->tokenize() parses quoted arguments');

    $input = new TestInput1('\"quoted\"');
    $this->assertEquals($input->getTokens(), array('"quoted"'), '->tokenize() parses escaped-quoted arguments');

    $input = new TestInput1("\'quoted\'");
    $this->assertEquals($input->getTokens(), array('\'quoted\''), '->tokenize() parses escaped-quoted arguments');

    $input = new TestInput1('-a');
    $this->assertEquals($input->getTokens(), array('-a'), '->tokenize() parses short options');

    $input = new TestInput1('-azc');
    $this->assertEquals($input->getTokens(), array('-azc'), '->tokenize() parses aggregated short options');

    $input = new TestInput1('-awithavalue');
    $this->assertEquals($input->getTokens(), array('-awithavalue'), '->tokenize() parses short options with a value');

    $input = new TestInput1('-a"foo bar"');
    $this->assertEquals($input->getTokens(), array('-afoo bar'), '->tokenize() parses short options with a value');

    $input = new TestInput1('-a"foo bar""foo bar"');
    $this->assertEquals($input->getTokens(), array('-afoo barfoo bar'), '->tokenize() parses short options with a value');

    $input = new TestInput1('-a\'foo bar\'');
    $this->assertEquals($input->getTokens(), array('-afoo bar'), '->tokenize() parses short options with a value');

    $input = new TestInput1('-a\'foo bar\'\'foo bar\'');
    $this->assertEquals($input->getTokens(), array('-afoo barfoo bar'), '->tokenize() parses short options with a value');

    $input = new TestInput1('-a\'foo bar\'"foo bar"');
    $this->assertEquals($input->getTokens(), array('-afoo barfoo bar'), '->tokenize() parses short options with a value');

    $input = new TestInput1('--long-option');
    $this->assertEquals($input->getTokens(), array('--long-option'), '->tokenize() parses long options');

    $input = new TestInput1('--long-option=foo');
    $this->assertEquals($input->getTokens(), array('--long-option=foo'), '->tokenize() parses long options with a value');

    $input = new TestInput1('--long-option="foo bar"');
    $this->assertEquals($input->getTokens(), array('--long-option=foo bar'), '->tokenize() parses long options with a value');

    $input = new TestInput1('--long-option="foo bar""another"');
    $this->assertEquals($input->getTokens(), array('--long-option=foo baranother'), '->tokenize() parses long options with a value');

    $input = new TestInput1('--long-option=\'foo bar\'');
    $this->assertEquals($input->getTokens(), array('--long-option=foo bar'), '->tokenize() parses long options with a value');

    $input = new TestInput1("--long-option='foo bar''another'");
    $this->assertEquals($input->getTokens(), array("--long-option=foo baranother"), '->tokenize() parses long options with a value');

    $input = new TestInput1("--long-option='foo bar'\"another\"");
    $this->assertEquals($input->getTokens(), array("--long-option=foo baranother"), '->tokenize() parses long options with a value');

    $input = new TestInput1('foo -a -ffoo --long bar');
    $this->assertEquals($input->getTokens(), array('foo', '-a', '-ffoo', '--long', 'bar'), '->tokenize() parses when several arguments and options');
  }
}

class TestInput1 extends StringInput
{
  public function getTokens()
  {
    return $this->tokens;
  }
}
