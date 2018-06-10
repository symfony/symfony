<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Symfony\Component\ExpressionLanguage\Expression;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * ExpressionExtension gives a way to create Expressions from a template.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ExpressionExtension extends AbstractExtension
{
    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return array(
            new TwigFunction('expression', array($this, 'createExpression')),
        );
    }

    public function createExpression($expression)
    {
        return new Expression($expression);
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'expression';
    }
}
