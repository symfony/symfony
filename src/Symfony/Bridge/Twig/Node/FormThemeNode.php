<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Node;

use Symfony\Component\Form\FormRenderer;
use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Node;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
#[YieldReady]
final class FormThemeNode extends Node
{
    /**
     * @param bool $only
     */
    public function __construct(Node $form, Node $resources, int $lineno, $only = false)
    {
        if (null === $only || \is_string($only)) {
            trigger_deprecation('symfony/twig-bridge', '3.12', 'Passing a tag to %s() is deprecated.', __METHOD__);
            $only = \func_num_args() > 4 ? func_get_arg(4) : true;
        } elseif (!\is_bool($only)) {
            throw new \TypeError(\sprintf('Argument 4 passed to "%s()" must be a boolean, "%s" given.', __METHOD__, get_debug_type($only)));
        }

        parent::__construct(['form' => $form, 'resources' => $resources], ['only' => $only], $lineno);
    }

    public function compile(Compiler $compiler): void
    {
        $compiler
            ->addDebugInfo($this)
            ->write('$this->env->getRuntime(')
            ->string(FormRenderer::class)
            ->raw(')->setTheme(')
            ->subcompile($this->getNode('form'))
            ->raw(', ')
            ->subcompile($this->getNode('resources'))
            ->raw(', ')
            ->raw(false === $this->getAttribute('only') ? 'true' : 'false')
            ->raw(");\n");
    }
}
