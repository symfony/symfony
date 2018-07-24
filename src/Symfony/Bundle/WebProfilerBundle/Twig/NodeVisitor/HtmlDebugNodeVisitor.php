<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Twig\NodeVisitor;

use Symfony\Bundle\WebProfilerBundle\Twig\Node\HtmlDebugEnterComment;
use Symfony\Bundle\WebProfilerBundle\Twig\Node\HtmlDebugLeaveComment;

/**
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
class HtmlDebugNodeVisitor extends \Twig_BaseNodeVisitor
{
    public const TEMPLATE = 'TEMPLATE';
    public const BLOCK = 'BLOCK';
    public const MACRO = 'MACRO';

    public function getPriority(): int
    {
        return 0;
    }

    protected function doEnterNode(\Twig_Node $node, \Twig_Environment $env)
    {
        return $node;
    }

    protected function doLeaveNode(\Twig_Node $node, \Twig_Environment $env)
    {
        if (!$this->supports($node)) {
            return $node;
        }

        $nodeName = $node->hasAttribute('name') ? $node->getAttribute('name') : '';
        $templateName = $node->getTemplateName();
        $hash = md5($nodeName.$templateName);
        $line = $node->getTemplateLine();

        if ($node instanceof \Twig_Node_Module) {
            $node->setNode('display_start', new \Twig_Node(array(
                new HtmlDebugEnterComment(self::TEMPLATE, $hash, $nodeName, $templateName, $line),
                $node->getNode('display_start'),
            )));
            $node->setNode('display_end', new \Twig_Node(array(
                $node->getNode('display_end'),
                new HtmlDebugLeaveComment(self::TEMPLATE, $hash),
            )));
        } elseif ($node instanceof \Twig_Node_Block) {
            $node->setNode('body', new \Twig_Node_Body(array(
                new HtmlDebugEnterComment(self::BLOCK, $hash, $nodeName, $templateName, $line),
                $node->getNode('body'),
                new HtmlDebugLeaveComment(self::BLOCK, $hash),
            )));
        } elseif ($node instanceof \Twig_Node_Macro) {
            $node->setNode('body', new \Twig_Node_Body(array(
                new HtmlDebugEnterComment(self::MACRO, $hash, $nodeName, $templateName, $line),
                $node->getNode('body'),
                new HtmlDebugLeaveComment(self::MACRO, $hash),
            )));
        }

        return $node;
    }

    private function supports(\Twig_Node $node): bool
    {
        if (!$node instanceof \Twig_Node_Module && !$node instanceof \Twig_Node_Block && !$node instanceof \Twig_Node_Macro) {
            return false;
        }

        if ('.html.twig' !== substr($name = $node->getTemplateName(), -10)) {
            return false;
        }

        return false === strpos($name, '@WebProfiler/');
    }
}
