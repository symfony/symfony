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
 * Creates new {@link ExecutionContext} instances.
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ExecutionContextFactory implements ExecutionContextFactoryInterface
{
    /**
     * @var GroupManagerInterface
     */
    private $groupManager;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var string|null
     */
    private $translationDomain;

    /**
     * Creates a new context factory.
     *
     * @param GroupManagerInterface $groupManager      The manager for accessing
     *                                                 the currently validated
     *                                                 group
     * @param TranslatorInterface   $translator        The translator
     * @param string|null           $translationDomain The translation domain to
     *                                                 use for translating
     *                                                 violation messages
     */
    public function __construct(GroupManagerInterface $groupManager, TranslatorInterface $translator, $translationDomain = null)
    {
        $this->groupManager = $groupManager;
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
            $this->groupManager,
            $this->translator,
            $this->translationDomain
        );
    }
}
