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

trigger_error('The '.__NAMESPACE__.'\LegacyExecutionContext class is deprecated since version 2.5 and will be removed in 3.0.', E_USER_DEPRECATED);

use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\MetadataFactoryInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * An execution context that is compatible with the legacy API (< 2.5).
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated since version 2.5, to be removed in 3.0.
 */
class LegacyExecutionContext extends ExecutionContext
{
    /**
     * @var MetadataFactoryInterface
     */
    private $metadataFactory;

    /**
     * Creates a new context.
     *
     * @see ExecutionContext::__construct()
     *
     * @internal Called by {@link LegacyExecutionContextFactory}. Should not be used
     *           in user code.
     */
    public function __construct(ValidatorInterface $validator, $root, MetadataFactoryInterface $metadataFactory, TranslatorInterface $translator, $translationDomain = null)
    {
        parent::__construct(
            $validator,
            $root,
            $translator,
            $translationDomain
        );

        $this->metadataFactory = $metadataFactory;
    }
}
