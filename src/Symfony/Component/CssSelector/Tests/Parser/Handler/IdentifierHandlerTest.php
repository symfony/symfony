<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\Tests\Parser\Handler;

use Symfony\Component\CssSelector\Parser\Handler\IdentifierHandler;
use Symfony\Component\CssSelector\Parser\Token;
use Symfony\Component\CssSelector\Parser\Tokenizer\TokenizerEscaping;
use Symfony\Component\CssSelector\Parser\Tokenizer\TokenizerPatterns;

class IdentifierHandlerTest extends AbstractHandlerTest
{
    public function getHandleValueTestData()
    {
        return [
            ['foo', new Token(Token::TYPE_IDENTIFIER, 'foo', 0), ''],
            ['foo|bar', new Token(Token::TYPE_IDENTIFIER, 'foo', 0), '|bar'],
            ['foo.class', new Token(Token::TYPE_IDENTIFIER, 'foo', 0), '.class'],
            ['foo[attr]', new Token(Token::TYPE_IDENTIFIER, 'foo', 0), '[attr]'],
            ['foo bar', new Token(Token::TYPE_IDENTIFIER, 'foo', 0), ' bar'],
        ];
    }

    public function getDontHandleValueTestData()
    {
        return [
            ['>'],
            ['+'],
            [' '],
            ['*|foo'],
            ['/* comment */'],
        ];
    }

    protected function generateHandler()
    {
        $patterns = new TokenizerPatterns();

        return new IdentifierHandler($patterns, new TokenizerEscaping($patterns));
    }
}
