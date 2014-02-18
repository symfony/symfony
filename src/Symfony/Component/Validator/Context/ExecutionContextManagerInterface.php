<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Context;

/**
 * @since  %%NextVersion%%
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface ExecutionContextManagerInterface
{
    /**
     * @param mixed $root
     *
     * @return ExecutionContextInterface The started context
     */
    public function startContext($root);

    /**
     * @return ExecutionContextInterface The stopped context
     */
    public function stopContext();

    /**
     * @return ExecutionContextInterface The current context
     */
    public function getCurrentContext();
}
