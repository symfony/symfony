<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\Parser;

use Symfony\Component\CssSelector\Parser\Shortcut\ClassParser;
use Symfony\Component\CssSelector\Parser\Shortcut\ElementParser;
use Symfony\Component\CssSelector\Parser\Shortcut\HashParser;
use Symfony\Component\CssSelector\Parser\Tokenizer\Tokenizer;

/**
 * CSS selector parser.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class Parser implements ParserInterface
{
    /**
     * @var Tokenizer
     */
    private $tokenizer;

    /**
     * @var ParserInterface[]
     */
    private $shortcuts;

    public function __construct()
    {
        $this->tokenizer = new Tokenizer();
        $this->shortcuts = array(
            new ElementParser(),
            new HashParser(),
            new ClassParser(),
        );
    }

    public function parse($source)
    {
        foreach ($this->shortcuts as $shortcut) {
            $tokens = $shortcut->parse($source);

            if (!empty($tokens)) {
                return $tokens;
            }
        }

        $reader = new Reader($source);
        $stream = $this->tokenizer->tokenize($reader);

        return iterator_to_array(new SelectorIterator($stream));
    }
}
