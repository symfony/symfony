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
use Symfony\Component\Validator\Exception\InvalidArgumentException;
use Symfony\Component\Validator\ExecutionContextInterface as LegacyExecutionContextInterface;
use Symfony\Component\Validator\Group\GroupManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\ValidatorInterface as LegacyValidatorInterface;

/**
 * @since  %%NextVersion%%
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LegacyExecutionContext extends ExecutionContext implements LegacyExecutionContextInterface
{
    public function __construct($root, ValidatorInterface $validator, GroupManagerInterface $groupManager, TranslatorInterface $translator, $translationDomain = null)
    {
        if (!$validator instanceof LegacyValidatorInterface) {
            throw new InvalidArgumentException(
                'The validator passed to LegacyExecutionContext must implement '.
                '"Symfony\Component\Validator\ValidatorInterface".'
            );
        }

        parent::__construct($root, $validator, $groupManager, $translator, $translationDomain);
    }

    /**
     * {@inheritdoc}
     */
    public function addViolation($message, array $parameters = array(), $invalidValue = null, $pluralization = null, $code = null)
    {
        if (func_num_args() >= 3) {
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

    public function addViolationAt($subPath, $message, array $parameters = array(), $invalidValue = null, $pluralization = null, $code = null)
    {
        if (func_num_args() >= 3) {
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

    public function validate($value, $subPath = '', $groups = null, $traverse = false, $deep = false)
    {
        // TODO handle $traverse and $deep

        return $this
            ->getValidator()
            ->inContext($this)
            ->atPath($subPath)
            ->validateObject($value, $groups)
        ;
    }

    public function validateValue($value, $constraints, $subPath = '', $groups = null)
    {
        return $this
            ->getValidator()
            ->inContext($this)
            ->atPath($subPath)
            ->validateValue($value, $constraints, $groups)
        ;
    }

    public function getMetadataFactory()
    {
        return $this->getValidator()->getMetadataFactory();
    }
}
