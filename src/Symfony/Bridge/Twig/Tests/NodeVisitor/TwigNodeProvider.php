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
use Twig\Node\BodyNode;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\Source;

class TwigNodeProvider
{
    public static function getModule($content)
    {
        return new ModuleNode(
            new ConstantExpression($content, 0),
            null,
            new ArrayExpression(array(), 0),
            new ArrayExpression(array(), 0),
            new ArrayExpression(array(), 0),
            null,
            new Source('', '')
        );
    }

    public static function getTransFilter($message, $domain = null, $arguments = null)
    {
        if (!$arguments) {
            $arguments = $domain ? array(
                new ArrayExpression(array(), 0),
                new ConstantExpression($domain, 0),
            ) : array();
        }

        return new FilterExpression(
            new ConstantExpression($message, 0),
            new ConstantExpression('trans', 0),
            new Node($arguments),
            0
        );
    }

    public static function getTransChoiceFilter($message, $domain = null, $arguments = null)
    {
        if (!$arguments) {
            $arguments = $domain ? array(
                new ConstantExpression(0, 0),
                new ArrayExpression(array(), 0),
                new ConstantExpression($domain, 0),
            ) : array();
        }

        return new FilterExpression(
            new ConstantExpression($message, 0),
            new ConstantExpression('transchoice', 0),
            new Node($arguments),
            0
        );
    }

    public static function getTransTag($message, $domain = null)
    {
        return new TransNode(
            new BodyNode(array(), array('data' => $message)),
            $domain ? new ConstantExpression($domain, 0) : null
        );
    }

    public static function getTransDefaultDomainTag($domain)
    {
        return new TransDefaultDomainNode(
            new ConstantExpression($domain, 0)
        );
    }
}
