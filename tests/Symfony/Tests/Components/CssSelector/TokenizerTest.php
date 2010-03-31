<?php

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\CssSelector;

use Symfony\Components\CssSelector\Tokenizer;

class TokenizerTest extends \PHPUnit_Framework_TestCase
{
  public function testTokenize()
  {
    $tokenizer = new Tokenizer();

    $tests = array(
      'h1',
      'h1:nth-child(3n+1)',
      'h1 > p',
      'h1#foo',
      'h1.foo',
      'h1[class*=foo]',
      'h1 .foo',
      'h1 #foo',
      'h1 [class*=foo]',
    );

    foreach ($tests as $test)
    {
      $this->assertEquals($test, $this->tokensToString($tokenizer->tokenize($test)), '->tokenize() lexes an input string and returns an array of tokens');
    }

    $this->assertEquals('foo[class=foo bar  ]', $this->tokensToString($tokenizer->tokenize('foo[class="foo bar"]')), '->tokenize() lexes an input string and returns an array of tokens');
    $this->assertEquals("foo[class=foo Abar     ]", $this->tokensToString($tokenizer->tokenize('foo[class="foo \\65 bar"]')), '->tokenize() lexes an input string and returns an array of tokens');
  }

  protected function tokensToString($tokens)
  {
    $str = '';
    foreach ($tokens as $token)
    {
      $str .= str_repeat(' ', $token->getPosition() - strlen($str)).$token;
    }

    return $str;
  }
}
