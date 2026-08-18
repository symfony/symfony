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

use Symfony\Bridge\Twig\Node\TransDefaultDomainNode;
use Symfony\Bridge\Twig\Node\TransNode;
use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Node\BlockNode;
use Twig\Node\EmptyNode;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Expression\Variable\AssignContextVariable;
use Twig\Node\Expression\Variable\ContextVariable;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\Node\Nodes;
use Twig\Node\SetNode;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class TranslationDefaultDomainNodeVisitor implements NodeVisitorInterface
{
    private const FOR_SELF_ATTRIBUTE = 'trans_default_domain_for_self';

    private Scope $scope;
    private ?ModuleNode $module = null;
    private int $blockDepth = 0;

    public function __construct(
        private readonly TransDefaultDomainRegistry $registry = new TransDefaultDomainRegistry(),
    ) {
        $this->scope = new Scope();
    }

    public function enterNode(Node $node, Environment $env): Node
    {
        if ($node instanceof ModuleNode) {
            // a module is always the root of a traversal, so start from a pristine scope:
            // compiling a template that throws leaves the previous scope unbalanced otherwise
            $this->scope = new Scope();
        }

        if ($node instanceof BlockNode || $node instanceof ModuleNode) {
            $this->scope = $this->scope->enter();
        }

        if ($node instanceof ModuleNode) {
            $this->module = $node;
            $this->blockDepth = 0;

            if (null !== $domain = $this->registry->getDomain($node->getSourceContext())) {
                $this->scope->set('domain', new ConstantExpression($domain, $node->getTemplateLine()));
            }
        }

        if ($node instanceof BlockNode) {
            ++$this->blockDepth;
        }

        if ($node instanceof TransDefaultDomainNode) {
            if ($node->getAttribute('for_self')) {
                $this->checkForSelfDeclaration($node);

                // the domain has already been applied to the whole template when entering the ModuleNode
                return $node;
            }

            if ($node->getNode('expr') instanceof ConstantExpression) {
                $this->scope->set('domain', $node->getNode('expr'));

                return $node;
            }

            if (null === $templateName = $node->getTemplateName()) {
                throw new \LogicException('Cannot traverse a node without a template name.');
            }

            $var = '__internal_trans_default_domain'.hash('xxh128', $templateName);

            $name = new AssignContextVariable($var, $node->getTemplateLine());
            $this->scope->set('domain', new ContextVariable($var, $node->getTemplateLine()));

            return new SetNode(false, new Nodes([$name]), new Nodes([$node->getNode('expr')]), $node->getTemplateLine());
        }

        if (!$this->scope->has('domain')) {
            return $node;
        }

        if ($node instanceof FilterExpression && 'trans' === ($node->hasAttribute('twig_callable') ? $node->getAttribute('twig_callable')->getName() : $node->getNode('filter')->getAttribute('value'))) {
            $arguments = $node->getNode('arguments');

            if ($arguments instanceof EmptyNode) {
                $arguments = new Nodes();
                $node->setNode('arguments', $arguments);
            }

            if ($this->isNamedArguments($arguments)) {
                if (!$arguments->hasNode('domain') && !$arguments->hasNode(1)) {
                    $arguments->setNode('domain', $this->scope->get('domain'));
                }
            } elseif (!$arguments->hasNode(1)) {
                if (!$arguments->hasNode(0)) {
                    $arguments->setNode(0, new ArrayExpression([], $node->getTemplateLine()));
                }

                $arguments->setNode(1, $this->scope->get('domain'));
            }
        } elseif ($node instanceof TransNode) {
            if (!$node->hasNode('domain')) {
                $node->setNode('domain', $this->scope->get('domain'));
            }
        }

        return $node;
    }

    public function leaveNode(Node $node, Environment $env): ?Node
    {
        if ($node instanceof TransDefaultDomainNode) {
            return null;
        }

        if ($node instanceof BlockNode) {
            --$this->blockDepth;
        }

        if ($node instanceof BlockNode || $node instanceof ModuleNode) {
            $this->scope = $this->scope->leave();
        }

        if ($node instanceof ModuleNode) {
            foreach ($node->getAttribute('embedded_templates') as $embeddedTemplate) {
                if ($embeddedTemplate->hasAttribute(self::FOR_SELF_ATTRIBUTE)) {
                    throw new SyntaxError('The "trans_default_domain" tag cannot be used with "for _self" inside an "embed" tag.', $embeddedTemplate->getTemplateLine(), $node->getSourceContext());
                }
            }

            $this->registry->markTemplateParsed($node->getSourceContext());
        }

        return $node;
    }

    public function getPriority(): int
    {
        return -10;
    }

    private function checkForSelfDeclaration(TransDefaultDomainNode $node): void
    {
        if ($this->blockDepth) {
            throw new SyntaxError('The "trans_default_domain" tag cannot be used with "for _self" inside a block.', $node->getTemplateLine(), $node->getSourceContext());
        }

        if ($this->module->hasAttribute(self::FOR_SELF_ATTRIBUTE)) {
            throw new SyntaxError('The "trans_default_domain" tag can be used with "for _self" only once per template.', $node->getTemplateLine(), $node->getSourceContext());
        }

        $this->module->setAttribute(self::FOR_SELF_ATTRIBUTE, true);
    }

    private function isNamedArguments(Node $arguments): bool
    {
        foreach ($arguments as $name => $node) {
            if (!\is_int($name)) {
                return true;
            }
        }

        return false;
    }
}
