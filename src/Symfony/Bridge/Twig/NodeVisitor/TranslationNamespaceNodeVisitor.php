<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\NodeVisitor;

use Symfony\Bridge\Twig\Node\TransNode;
use Symfony\Bridge\Twig\Node\TransNamespaceNode;

/**
 * @author Abdellatif Ait boudad <a.aitboudad@gmail.com>
 */
class TranslationNamespaceNodeVisitor extends \Twig_BaseNodeVisitor
{
    /**
     * @var Scope
     */
    private $scope;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->scope = new Scope();
    }

    /**
     * {@inheritdoc}
     */
    protected function doEnterNode(\Twig_Node $node, \Twig_Environment $env)
    {
        if ($node instanceof \Twig_Node_Block || $node instanceof \Twig_Node_Module) {
            $this->scope = $this->scope->enter();
        }

        if ($node instanceof TransNamespaceNode) {
            if ($node->getNode('expr') instanceof \Twig_Node_Expression_Constant) {
                $this->scope->set('namespace', $node->getNode('expr'));

                return $node;
            } else {
                $var = $env->getParser()->getVarName();
                $name = new \Twig_Node_Expression_AssignName($var, $node->getLine());
                $this->scope->set('namespace', new \Twig_Node_Expression_Name($var, $node->getLine()));

                return new \Twig_Node_Set(false, new \Twig_Node(array($name)), new \Twig_Node(array($node->getNode('expr'))), $node->getLine());
            }
        }

        if (!$this->scope->has('namespace')) {
            return $node;
        }

        $namespace = $this->scope->get('namespace')->getAttribute('value');
        if ($node instanceof \Twig_Node_Expression_Filter && in_array($node->getNode('filter')->getAttribute('value'), array('trans', 'transchoice'))) {
            $message = $node->getNode('node')->getAttribute('value');
            $node->getNode('node')->setAttribute('value', $namespace.$message);
        } elseif ($node instanceof TransNode) {
            $message = $node->getNode('body')->getAttribute('data');
            $node->getNode('body')->setAttribute('data', $namespace.$message);
        }

        return $node;
    }

    /**
     * {@inheritdoc}
     */
    protected function doLeaveNode(\Twig_Node $node, \Twig_Environment $env)
    {
        if ($node instanceof TransNamespaceNode) {
            return false;
        }

        if ($node instanceof \Twig_Node_Block || $node instanceof \Twig_Node_Module) {
            $this->scope = $this->scope->leave();
        }

        return $node;
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority()
    {
        return -10;
    }
}
