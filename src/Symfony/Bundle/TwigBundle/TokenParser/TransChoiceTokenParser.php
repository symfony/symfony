<?php

namespace Symfony\Bundle\TwigBundle\TokenParser;

use Symfony\Bundle\TwigBundle\Node\TransNode;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * 
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class TransChoiceTokenParser extends TransTokenParser
{
    /**
     * Parses a token and returns a node.
     *
     * @param  \Twig_Token $token A Twig_Token instance
     *
     * @return \Twig_NodeInterface A Twig_NodeInterface instance
     */
    public function parse(\Twig_Token $token)
    {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();

        $vars = null;

        $count = $this->parser->getExpressionParser()->parseExpression();

        $domain = new \Twig_Node_Expression_Constant('messages', $lineno);

        if ($stream->test('with')) {
            // {% transchoice count with vars %}
            $stream->next();
            $vars = $this->parser->getExpressionParser()->parseExpression();
        }

        if ($stream->test('from')) {
            // {% transchoice count from "messages" %}
            $stream->next();
            $domain = $this->parser->getExpressionParser()->parseExpression();
        }

        $stream->expect(\Twig_Token::BLOCK_END_TYPE);

        $body = $this->parser->subparse(array($this, 'decideTransChoiceFork'), true);

        $stream->expect(\Twig_Token::BLOCK_END_TYPE);

        return new TransNode($body, $domain, $count, $vars, $this->isSimpleString($body), $lineno, $this->getTag());
    }

    public function decideTransChoiceFork($token)
    {
        return $token->test(array('endtranschoice'));
    }

    /**
     * Gets the tag name associated with this token parser.
     *
     * @param string The tag name
     */
    public function getTag()
    {
        return 'transchoice';
    }
}
