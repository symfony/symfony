<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\NodeTraverser;

use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\NodeVisitor\NodeVisitorInterface;

/**
 * @since  %%NextVersion%%
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface NodeTraverserInterface
{
    public function addVisitor(NodeVisitorInterface $visitor);

    public function removeVisitor(NodeVisitorInterface $visitor);

    public function traverse(array $nodes, ExecutionContextInterface $context);
}
