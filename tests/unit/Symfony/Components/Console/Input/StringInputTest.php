<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../../bootstrap.php';

use Symfony\Components\Console\Input\StringInput;

class TestInput extends StringInput
{
  public function getTokens()
  {
    return $this->tokens;
  }
}

$t = new LimeTest(23);

// ->tokenize()
$t->diag('->tokenize()');
$input = new TestInput('');
$t->is($input->getTokens(), array(), '->tokenize() parses an empty string');

$input = new TestInput('foo');
$t->is($input->getTokens(), array('foo'), '->tokenize() parses arguments');

$input = new TestInput('  foo  bar  ');
$t->is($input->getTokens(), array('foo', 'bar'), '->tokenize() ignores whitespaces between arguments');

$input = new TestInput('"quoted"');
$t->is($input->getTokens(), array('quoted'), '->tokenize() parses quoted arguments');

$input = new TestInput("'quoted'");
$t->is($input->getTokens(), array('quoted'), '->tokenize() parses quoted arguments');

$input = new TestInput('\"quoted\"');
$t->is($input->getTokens(), array('"quoted"'), '->tokenize() parses escaped-quoted arguments');

$input = new TestInput("\'quoted\'");
$t->is($input->getTokens(), array('\'quoted\''), '->tokenize() parses escaped-quoted arguments');

$input = new TestInput('-a');
$t->is($input->getTokens(), array('-a'), '->tokenize() parses short options');

$input = new TestInput('-azc');
$t->is($input->getTokens(), array('-azc'), '->tokenize() parses aggregated short options');

$input = new TestInput('-awithavalue');
$t->is($input->getTokens(), array('-awithavalue'), '->tokenize() parses short options with a value');

$input = new TestInput('-a"foo bar"');
$t->is($input->getTokens(), array('-afoo bar'), '->tokenize() parses short options with a value');

$input = new TestInput('-a"foo bar""foo bar"');
$t->is($input->getTokens(), array('-afoo barfoo bar'), '->tokenize() parses short options with a value');

$input = new TestInput('-a\'foo bar\'');
$t->is($input->getTokens(), array('-afoo bar'), '->tokenize() parses short options with a value');

$input = new TestInput('-a\'foo bar\'\'foo bar\'');
$t->is($input->getTokens(), array('-afoo barfoo bar'), '->tokenize() parses short options with a value');

$input = new TestInput('-a\'foo bar\'"foo bar"');
$t->is($input->getTokens(), array('-afoo barfoo bar'), '->tokenize() parses short options with a value');

$input = new TestInput('--long-option');
$t->is($input->getTokens(), array('--long-option'), '->tokenize() parses long options');

$input = new TestInput('--long-option=foo');
$t->is($input->getTokens(), array('--long-option=foo'), '->tokenize() parses long options with a value');

$input = new TestInput('--long-option="foo bar"');
$t->is($input->getTokens(), array('--long-option=foo bar'), '->tokenize() parses long options with a value');

$input = new TestInput('--long-option="foo bar""another"');
$t->is($input->getTokens(), array('--long-option=foo baranother'), '->tokenize() parses long options with a value');

$input = new TestInput('--long-option=\'foo bar\'');
$t->is($input->getTokens(), array('--long-option=foo bar'), '->tokenize() parses long options with a value');

$input = new TestInput("--long-option='foo bar''another'");
$t->is($input->getTokens(), array("--long-option=foo baranother"), '->tokenize() parses long options with a value');

$input = new TestInput("--long-option='foo bar'\"another\"");
$t->is($input->getTokens(), array("--long-option=foo baranother"), '->tokenize() parses long options with a value');

$input = new TestInput('foo -a -ffoo --long bar');
$t->is($input->getTokens(), array('foo', '-a', '-ffoo', '--long', 'bar'), '->tokenize() parses when several arguments and options');
