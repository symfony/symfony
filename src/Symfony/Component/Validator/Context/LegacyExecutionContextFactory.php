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
use Symfony\Component\Validator\MetadataFactoryInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Creates new {@link LegacyExecutionContext} instances.
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated Implemented for backwards compatibility with Symfony < 2.5.
 *             To be removed in Symfony 3.0.
 */
class LegacyExecutionContextFactory implements ExecutionContextFactoryInterface
{
    /**
     * @var MetadataFactoryInterface
     */
    private $metadataFactory;

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
     * @param MetadataFactoryInterface $metadataFactory   The metadata factory
     * @param TranslatorInterface      $translator        The translator
     * @param string|null              $translationDomain The translation domain
     *                                                    to use for translating
     *                                                    violation messages
     */
    public function __construct(MetadataFactoryInterface $metadataFactory, TranslatorInterface $translator, $translationDomain = null)
    {
        $this->metadataFactory = $metadataFactory;
        $this->translator = $translator;
        $this->translationDomain = $translationDomain;
    }

    /**
     * {@inheritdoc}
     */
    public function createContext(ValidatorInterface $validator, $root)
    {
        return new LegacyExecutionContext(
            $validator,
            $root,
            $this->metadataFactory,
            $this->translator,
            $this->translationDomain
        );
    }
}
