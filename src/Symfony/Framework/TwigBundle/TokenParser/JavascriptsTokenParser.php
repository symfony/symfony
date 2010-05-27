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
 * Wrapper for the javascripts helper output() method.
 *
 * {% javascripts %}
 *
 * @package    Symfony
 * @subpackage Framework_TwigBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class JavascriptsTokenParser extends \Twig_TokenParser
{
    public function parse(\Twig_Token $token)
    {
        $this->parser->getStream()->expect(\Twig_Token::BLOCK_END_TYPE);

        return new HelperNode('javascripts', 'render', new \Twig_NodeList(array()), true, $token->getLine(), $this->getTag());
    }

    public function getTag()
    {
        return 'javascripts';
    }
}
