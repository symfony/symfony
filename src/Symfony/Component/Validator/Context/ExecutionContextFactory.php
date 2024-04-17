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

use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Creates new {@link ExecutionContext} instances.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
class ExecutionContextFactory implements ExecutionContextFactoryInterface
{
    public function __construct(
        private TranslatorInterface $translator,
        private ?string $translationDomain = null,
    ) {
    }

    public function createContext(ValidatorInterface $validator, mixed $root): ExecutionContextInterface
    {
        return new ExecutionContext(
            $validator,
            $root,
            $this->translator,
            $this->translationDomain
        );
    }
}
