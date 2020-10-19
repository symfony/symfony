<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Violation;

use Symfony\Component\Translation\TranslatorInterface as LegacyTranslatorInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Util\PropertyPath;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Default implementation of {@link ConstraintViolationBuilderInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal since version 2.5. Code against ConstraintViolationBuilderInterface instead.
 */
class ConstraintViolationBuilder implements ConstraintViolationBuilderInterface
{
    private $violations;
    private $message;
    private $parameters;
    private $root;
    private $invalidValue;
    private $propertyPath;
    private $translator;
    private $translationDomain;
    private $plural;
    private $constraint;
    private $code;

    /**
     * @var mixed
     */
    private $cause;

    /**
     * @param string|object       $message    The error message as a string or a stringable object
     * @param TranslatorInterface $translator
     */
    public function __construct(ConstraintViolationList $violations, Constraint $constraint, $message, array $parameters, $root, string $propertyPath, $invalidValue, $translator, string $translationDomain = null)
    {
        if (null === $message) {
            @trigger_error(sprintf('Passing a null message when instantiating a "%s" is deprecated since Symfony 4.4.', __CLASS__), \E_USER_DEPRECATED);
            $message = '';
        }
        if (!$translator instanceof LegacyTranslatorInterface && !$translator instanceof TranslatorInterface) {
            throw new \TypeError(sprintf('Argument 8 passed to "%s()" must be an instance of "%s", "%s" given.', __METHOD__, TranslatorInterface::class, \is_object($translator) ? \get_class($translator) : \gettype($translator)));
        }
        $this->violations = $violations;
        $this->message = $message;
        $this->parameters = $parameters;
        $this->root = $root;
        $this->propertyPath = $propertyPath;
        $this->invalidValue = $invalidValue;
        $this->translator = $translator;
        $this->translationDomain = $translationDomain;
        $this->constraint = $constraint;
    }

    /**
     * {@inheritdoc}
     */
    public function atPath($path)
    {
        $this->propertyPath = PropertyPath::append($this->propertyPath, $path);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setParameter($key, $value)
    {
        $this->parameters[$key] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setParameters(array $parameters)
    {
        $this->parameters = $parameters;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setTranslationDomain($translationDomain)
    {
        $this->translationDomain = $translationDomain;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setInvalidValue($invalidValue)
    {
        $this->invalidValue = $invalidValue;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setPlural($number)
    {
        $this->plural = $number;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setCode($code)
    {
        if (null !== $code && !\is_string($code)) {
            @trigger_error(sprintf('Not using a string as the error code in %s() is deprecated since Symfony 4.4. A type-hint will be added in 5.0.', __METHOD__), \E_USER_DEPRECATED);
        }

        $this->code = $code;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setCause($cause)
    {
        $this->cause = $cause;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addViolation()
    {
        if (null === $this->plural) {
            $translatedMessage = $this->translator->trans(
                $this->message,
                $this->parameters,
                $this->translationDomain
            );
        } elseif ($this->translator instanceof TranslatorInterface) {
            $translatedMessage = $this->translator->trans(
                $this->message,
                ['%count%' => $this->plural] + $this->parameters,
                $this->translationDomain
            );
        } else {
            try {
                $translatedMessage = $this->translator->transChoice(
                    $this->message,
                    $this->plural,
                    $this->parameters,
                    $this->translationDomain
                );
            } catch (\InvalidArgumentException $e) {
                $translatedMessage = $this->translator->trans(
                    $this->message,
                    $this->parameters,
                    $this->translationDomain
                );
            }
        }

        $this->violations->add(new ConstraintViolation(
            $translatedMessage,
            $this->message,
            $this->parameters,
            $this->root,
            $this->propertyPath,
            $this->invalidValue,
            $this->plural,
            $this->code,
            $this->constraint,
            $this->cause
        ));
    }
}
