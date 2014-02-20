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
use Symfony\Component\Validator\Constraints\Traverse;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Group\GroupManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\ValidatorInterface as LegacyValidatorInterface;

/**
 * An execution context that is compatible with the legacy API (< 2.5).
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated Implemented for backwards compatibility with Symfony < 2.5. To be
 *             removed in 3.0.
 */
class LegacyExecutionContext extends ExecutionContext
{
    /**
     * Creates a new context.
     *
     * This constructor ensures that the given validator implements the
     * deprecated {@link \Symfony\Component\Validator\ValidatorInterface}. If
     * it does not, an {@link InvalidArgumentException} is thrown.
     *
     * @see ExecutionContext::__construct()
     *
     * @internal Called by {@link LegacyExecutionContextFactory}. Should not be used
     *           in user code.
     */
    public function __construct(ValidatorInterface $validator, $root, GroupManagerInterface $groupManager, TranslatorInterface $translator, $translationDomain = null)
    {
        if (!$validator instanceof LegacyValidatorInterface) {
            throw new InvalidArgumentException(
                'The validator passed to LegacyExecutionContext must implement '.
                '"Symfony\Component\Validator\ValidatorInterface".'
            );
        }

        parent::__construct(
            $validator,
            $root,
            $groupManager,
            $translator,
            $translationDomain
        );
    }

    /**
     * {@inheritdoc}
     */
    public function addViolation($message, array $parameters = array(), $invalidValue = null, $pluralization = null, $code = null)
    {
        if (func_num_args() > 2) {
            $this
                ->buildViolation($message, $parameters)
                ->setInvalidValue($invalidValue)
                ->setPluralization($pluralization)
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
    public function addViolationAt($subPath, $message, array $parameters = array(), $invalidValue = null, $pluralization = null, $code = null)
    {
        if (func_num_args() > 2) {
            $this
                ->buildViolation($message, $parameters)
                ->atPath($subPath)
                ->setInvalidValue($invalidValue)
                ->setPluralization($pluralization)
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
            $constraint = new Traverse(array(
                'traverse' => true,
                'deep' => $deep,
            ));

            return $this
                ->getValidator()
                ->inContext($this)
                ->atPath($subPath)
                ->validate($value, $constraint, $groups)
            ;
        }

        if ($traverse && $value instanceof \Traversable) {
            $constraints = array(
                new Valid(),
                new Traverse(array('traverse' => true, 'deep' => $deep)),
            );

            return $this
                ->getValidator()
                ->inContext($this)
                ->atPath($subPath)
                ->validate($value, $constraints, $groups)
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
        return $this->getValidator()->getMetadataFactory();
    }
}
