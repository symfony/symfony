<?php

namespace Symfony\Bundle\TwigBundle\TokenParser;

use Symfony\Bundle\TwigBundle\Node\IfRoleNode;

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
class IfRoleTokenParser extends \Twig_TokenParser
{
    /**
     * {@inheritdoc}
     */
    public function parse(\Twig_Token $token)
    {
        $stream = $this->parser->getStream();

        $role = $this->parser->getExpressionParser()->parseExpression();

        $object = null;
        if ($stream->test('for')) {
            $stream->next();
            $object = $this->parser->getExpressionParser()->parseExpression();
        }

        $stream->expect(\Twig_Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse(array($this, 'decideIfRoleFork'), true);
        $stream->expect(\Twig_Token::BLOCK_END_TYPE);

        return new IfRoleNode($role, $object, $body, $token->getLine(), $this->getTag());
    }

    public function decideIfRoleFork($token)
    {
        return $token->test(array('endifrole'));
    }

    /**
     * Gets the tag name associated with this token parser.
     *
     * @param string The tag name
     */
    public function getTag()
    {
        return 'ifrole';
    }
}
