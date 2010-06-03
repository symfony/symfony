<?php

namespace Symfony\Framework\TwigBundle\TokenParser;

use Symfony\Framework\TwigBundle\Node\HelperNode;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Wrapper for the stylesheets helper add() method.
 *
 * {% stylesheet 'bundles/blog/css/blog.css' with ['media': 'screen'] %}
 *
 * @package    Symfony
 * @subpackage Framework_TwigBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class StylesheetTokenParser extends \Twig_TokenParser
{
    public function parse(\Twig_Token $token)
    {
        $stream = $this->parser->getStream();

        $nodes = array($this->parser->getExpressionParser()->parseExpression());

        if ($stream->test(\Twig_Token::NAME_TYPE, 'with')) {
            $stream->next();

            $nodes[] = $this->parser->getExpressionParser()->parseExpression();
        }

        $stream->expect(\Twig_Token::BLOCK_END_TYPE);

        return new HelperNode('stylesheets', 'add', new \Twig_Node($nodes), false, $token->getLine(), $this->getTag());
    }

    public function getTag()
    {
        return 'stylesheet';
    }
}
