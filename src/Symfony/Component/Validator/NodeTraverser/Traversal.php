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

/**
 * @since  %%NextVersion%%
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Traversal
{
    public $context;

    public $nodeQueue;

    public function __construct(ExecutionContextInterface $context)
    {
        $this->context = $context;
        $this->nodeQueue = new \SplQueue();
    }
}
