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
use Twig\Attribute\FirstClassTwigCallableReady;
use Twig\Node\BodyNode;
use Twig\Node\EmptyNode;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\Node\Nodes;
use Twig\Source;
use Twig\TwigFilter;

class TwigNodeProvider
{
    public static function getModule($content)
    {
        $emptyNodeExists = class_exists(EmptyNode::class);

        return new ModuleNode(
            new BodyNode([new ConstantExpression($content, 0)]),
            null,
            $emptyNodeExists ? new EmptyNode() : new ArrayExpression([], 0),
            $emptyNodeExists ? new EmptyNode() : new ArrayExpression([], 0),
            $emptyNodeExists ? new EmptyNode() : new ArrayExpression([], 0),
            $emptyNodeExists ? new EmptyNode() : null,
            new Source('', '')
        );
    }

    public static function getTransFilter($message, $domain = null, $arguments = null)
    {
        if (!$arguments) {
            $arguments = $domain ? [
                new ArrayExpression([], 0),
                new ConstantExpression($domain, 0),
            ] : [];
        }

        if (class_exists(Nodes::class)) {
            $args = new Nodes($arguments);
        } else {
            $args = new Node($arguments);
        }

        if (!class_exists(FirstClassTwigCallableReady::class)) {
            return new FilterExpression(
                new ConstantExpression($message, 0),
                new ConstantExpression('trans', 0),
                $args,
                0
            );
        }

        return new FilterExpression(
            new ConstantExpression($message, 0),
            new TwigFilter('trans'),
            $args,
            0
        );
    }

    public static function getTransTag($message, $domain = null)
    {
        return new TransNode(
            new BodyNode([], ['data' => $message]),
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
