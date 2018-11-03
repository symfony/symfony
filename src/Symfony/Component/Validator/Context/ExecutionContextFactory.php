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

use Symfony\Component\Translation\TranslatorInterface as LegacyTranslatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Creates new {@link ExecutionContext} instances.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal version 2.5. Code against ExecutionContextFactoryInterface instead.
 */
class ExecutionContextFactory implements ExecutionContextFactoryInterface
{
    private $translator;
    private $translationDomain;

    /**
     * Creates a new context factory.
     *
     * @param TranslatorInterface $translator        The translator
     * @param string|null         $translationDomain The translation domain to
     *                                               use for translating
     *                                               violation messages
     */
    public function __construct($translator, string $translationDomain = null)
    {
        if (!$translator instanceof LegacyTranslatorInterface && !$translator instanceof TranslatorInterface) {
            throw new \TypeError(sprintf('Argument 1 passed to %s() must be an instance of %s, %s given.', __METHOD__, TranslatorInterface::class, \is_object($translator) ? \get_class($translator) : \gettype($translator)));
        }

        $this->translator = $translator;
        $this->translationDomain = $translationDomain;
    }

    /**
     * {@inheritdoc}
     */
    public function createContext(ValidatorInterface $validator, $root)
    {
        return new ExecutionContext(
            $validator,
            $root,
            $this->translator,
            $this->translationDomain
        );
    }
}
