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
 * Wrapper for the stylesheets helper output() method.
 *
 * {% stylesheets %}
 *
 * @package    Symfony
 * @subpackage Framework_TwigBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class StylesheetsTokenParser extends \Twig_TokenParser
{
    public function parse(\Twig_Token $token)
    {
        $this->parser->getStream()->expect(\Twig_Token::BLOCK_END_TYPE);

        return new HelperNode('stylesheets', 'render', null, true, $token->getLine(), $this->getTag());
    }

    public function getTag()
    {
        return 'stylesheets';
    }
}
