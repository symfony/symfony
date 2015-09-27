<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\NodeVisitor;

use Symfony\Bridge\Twig\Node\TransDefaultDomainNode;
use Symfony\Bridge\Twig\Node\TransNode;

class TwigNodeProvider
{
    public static function getModule($content)
    {
        return new \Twig_Node_Module(
            new \Twig_Node_Expression_Constant($content, 0),
            null,
            new \Twig_Node_Expression_Array(array(), 0),
            new \Twig_Node_Expression_Array(array(), 0),
            new \Twig_Node_Expression_Array(array(), 0),
            null,
            null
        );
    }

    public static function getTransFilter($message, $domain = null, $arguments = null)
    {
        if (!$arguments) {
            $arguments = $domain ? array(
                new \Twig_Node_Expression_Array(array(), 0),
                new \Twig_Node_Expression_Constant($domain, 0),
            ) : array();
        }

        return new \Twig_Node_Expression_Filter(
            new \Twig_Node_Expression_Constant($message, 0),
            new \Twig_Node_Expression_Constant('trans', 0),
            new \Twig_Node($arguments),
            0
        );
    }

    public static function getTransChoiceFilter($message, $domain = null, $arguments = null)
    {
        if (!$arguments) {
            $arguments = $domain ? array(
                new \Twig_Node_Expression_Constant(0, 0),
                new \Twig_Node_Expression_Array(array(), 0),
                new \Twig_Node_Expression_Constant($domain, 0),
            ) : array();
        }

        return new \Twig_Node_Expression_Filter(
            new \Twig_Node_Expression_Constant($message, 0),
            new \Twig_Node_Expression_Constant('transchoice', 0),
            new \Twig_Node($arguments),
            0
        );
    }

    public static function getTransTag($message, $domain = null)
    {
        return new TransNode(
            new \Twig_Node_Body(array(), array('data' => $message)),
            $domain ? new \Twig_Node_Expression_Constant($domain, 0) : null
        );
    }

    public static function getTransDefaultDomainTag($domain)
    {
        return new TransDefaultDomainNode(
            new \Twig_Node_Expression_Constant($domain, 0)
        );
    }
}
