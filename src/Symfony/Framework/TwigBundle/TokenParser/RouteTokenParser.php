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
 * Wrapper for the route helper generate() method.
 *
 * {% route 'blog_post' with ['id': post.id] %}
 *
 * @package    Symfony
 * @subpackage Framework_TwigBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class RouteTokenParser extends \Twig_TokenParser
{
    public function parse(\Twig_Token $token)
    {
        $nodes = array();

        $lineno = $token->getLine();
        $nodes[] = $this->parser->getExpressionParser()->parseExpression();

        if ($this->parser->getStream()->test(\Twig_Token::NAME_TYPE, 'with')) {
            $this->parser->getStream()->expect(\Twig_Token::NAME_TYPE, 'with');
            $nodes[] = $this->parser->getExpressionParser()->parseExpression();
        }

        $this->parser->getStream()->expect(\Twig_Token::BLOCK_END_TYPE);

        return new HelperNode('router', 'generate', new \Twig_NodeList($nodes), true, $lineno, $this->getTag());
    }

    public function getTag()
    {
        return 'route';
    }
}
