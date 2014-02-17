<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\NodeVisitor;

use Symfony\Component\Validator\Node\Node;

/**
 * @since  %%NextVersion%%
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface NodeVisitorInterface
{
    public function beforeTraversal(array $nodes);

    public function afterTraversal(array $nodes);

    public function enterNode(Node $node);

    public function leaveNode(Node $node);
}
