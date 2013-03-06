<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\Token;

use Symfony\Component\CssSelector\Token\Handler;

/**
 * CSS selector tokenizer.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class Tokenizer
{
    /**
     * @var Handler\HandlerInterface[]
     */
    private $handlers;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $patterns = new TokenizerPatterns();
        $escaping = new TokenizerEscaping();

        $this->handlers = array(
            new Handler\WhitespaceHandler(),
            new Handler\IdentifierHandler($patterns, $escaping),
            new Handler\HashHandler($patterns, $escaping),
            new Handler\StringHandler($patterns, $escaping),
            new Handler\NumberHandler($patterns),
            new Handler\CommentHandler(),
        );
    }

    /**
     * Tokenize selector source code.
     *
     * @param string $source
     *
     * @return TokenStream
     */
    public function tokenize($source)
    {
        $reader = new Reader($source);
        $stream = new TokenStream();

        while (!$reader->isEOF()) {
            foreach ($this->handlers as $handler) {
                if ($handler->handle($reader, $stream)) {
                    continue;
                }
            }

            $stream->push(new Token(Token::TYPE_DELIMITER, $reader->getSubstring(1), $reader->getPosition()));
            $reader->moveForward(1);
        }

        return $stream
            ->push(new Token(Token::TYPE_FILE_END, null, $reader->getPosition()))
            ->freeze();
    }
}
