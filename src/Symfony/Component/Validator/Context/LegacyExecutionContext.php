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
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\MetadataFactoryInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * An execution context that is compatible with the legacy API (< 2.5).
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated Implemented for backwards compatibility with Symfony < 2.5.
 *             To be removed in Symfony 3.0.
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

    /**
     * {@inheritdoc}
     */
    public function addViolation($message, array $parameters = array(), $invalidValue = null, $plural = null, $code = null)
    {
        if (func_num_args() > 2) {
            $this
                ->buildViolation($message, $parameters)
                ->setInvalidValue($invalidValue)
                ->setPlural($plural)
                ->setCode($code)
                ->addViolation()
            ;

            return;
        }

        parent::addViolation($message, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function addViolationAt($subPath, $message, array $parameters = array(), $invalidValue = null, $plural = null, $code = null)
    {
        if (func_num_args() > 2) {
            $this
                ->buildViolation($message, $parameters)
                ->atPath($subPath)
                ->setInvalidValue($invalidValue)
                ->setPlural($plural)
                ->setCode($code)
                ->addViolation()
            ;

            return;
        }

        $this
            ->buildViolation($message, $parameters)
            ->atPath($subPath)
            ->addViolation()
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function validate($value, $subPath = '', $groups = null, $traverse = false, $deep = false)
    {
        if (is_array($value)) {
            // The $traverse flag is ignored for arrays
            $constraint = new Valid(array('traverse' => true, 'deep' => $deep));

            return $this
                ->getValidator()
                ->inContext($this)
                ->atPath($subPath)
                ->validate($value, $constraint, $groups)
            ;
        }

        if ($traverse && $value instanceof \Traversable) {
            $constraint = new Valid(array('traverse' => true, 'deep' => $deep));

            return $this
                ->getValidator()
                ->inContext($this)
                ->atPath($subPath)
                ->validate($value, $constraint, $groups)
            ;
        }

        return $this
            ->getValidator()
            ->inContext($this)
            ->atPath($subPath)
            ->validate($value, null, $groups)
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function validateValue($value, $constraints, $subPath = '', $groups = null)
    {
        return $this
            ->getValidator()
            ->inContext($this)
            ->atPath($subPath)
            ->validate($value, $constraints, $groups)
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadataFactory()
    {
        return $this->metadataFactory;
    }
}
