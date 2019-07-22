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

use Symfony\Component\CssSelector\Parser\Handler\HashHandler;
use Symfony\Component\CssSelector\Parser\Token;
use Symfony\Component\CssSelector\Parser\Tokenizer\TokenizerEscaping;
use Symfony\Component\CssSelector\Parser\Tokenizer\TokenizerPatterns;

class HashHandlerTest extends AbstractHandlerTest
{
    public function getHandleValueTestData()
    {
        return [
            ['#id', new Token(Token::TYPE_HASH, 'id', 0), ''],
            ['#123', new Token(Token::TYPE_HASH, '123', 0), ''],

            ['#id.class', new Token(Token::TYPE_HASH, 'id', 0), '.class'],
            ['#id element', new Token(Token::TYPE_HASH, 'id', 0), ' element'],
        ];
    }

    public function getDontHandleValueTestData()
    {
        return [
            ['id'],
            ['123'],
            ['<'],
            ['<'],
            ['#'],
        ];
    }

    protected function generateHandler()
    {
        $patterns = new TokenizerPatterns();

        return new HashHandler($patterns, new TokenizerEscaping($patterns));
    }
}
