<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\CompatAssetsBundle\Twig\TokenParser;

use Symfony\Bundle\CompatAssetsBundle\Twig\Node\StylesheetsNode;

/**
 * 
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class StylesheetsTokenParser extends \Twig_TokenParser
{
    /**
     * Parses a token and returns a node.
     *
     * @param \Twig_Token $token A \Twig_Token instance
     *
     * @return \Twig_NodeInterface A \Twig_NodeInterface instance
     */
    public function parse(\Twig_Token $token)
    {
        $this->parser->getStream()->expect(\Twig_Token::BLOCK_END_TYPE);

        return new StylesheetsNode($token->getLine(), $this->getTag());
    }

    /**
     * Gets the tag name associated with this token parser.
     *
     * @param string The tag name
     */
    public function getTag()
    {
        return 'stylesheets';
    }
}
