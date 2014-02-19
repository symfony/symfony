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

use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Group\GroupManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * A context manager that creates contexts compatible to the API < Symfony 2.5.
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see ExecutionContextManagerInterface
 * @see \Symfony\Component\Validator\NodeVisitor\NodeVisitorInterface
 */
class LegacyExecutionContextManager extends ExecutionContextManager
{
    /**
     * {@inheritdoc}
     */
    protected function createContext($root, ValidatorInterface $validator, GroupManagerInterface $groupManager, TranslatorInterface $translator, $translationDomain)
    {
        return new LegacyExecutionContext($root, $validator, $groupManager, $translator, $translationDomain);
    }
}
