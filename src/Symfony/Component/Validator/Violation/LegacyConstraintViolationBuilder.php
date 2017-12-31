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

@trigger_error('The '.__NAMESPACE__.'\LegacyConstraintViolationBuilder class is deprecated since Symfony 2.5 and will be removed in 3.0.', E_USER_DEPRECATED);

use Symfony\Component\Validator\ExecutionContextInterface;

/**
 * Backwards-compatible implementation of {@link ConstraintViolationBuilderInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal You should not instantiate or use this class. Code against
 *           {@link ConstraintViolationBuilderInterface} instead.
 *
 * @deprecated since version 2.5.5, to be removed in 3.0.
 */
class LegacyConstraintViolationBuilder implements ConstraintViolationBuilderInterface
{
    /**
     * @var ExecutionContextInterface
     */
    private $context;

    /**
     * @var string
     */
    private $message;

    /**
     * @var array
     */
    private $parameters;

    /**
     * @var mixed
     */
    private $invalidValue;

    /**
     * @var string
     */
    private $propertyPath;

    /**
     * @var int|null
     */
    private $plural;

    /**
     * @var mixed
     */
    private $code;

    public function __construct(ExecutionContextInterface $context, $message, array $parameters)
    {
        $this->context = $context;
        $this->message = $message;
        $this->parameters = $parameters;
        $this->invalidValue = $context->getValue();
    }

    /**
     * {@inheritdoc}
     */
    public function atPath($path)
    {
        $this->propertyPath = $path;

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
        // can't be set in the old API

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
        $this->code = $code;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setCause($cause)
    {
        // do nothing - we can't save the cause through the old API

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addViolation()
    {
        if ($this->propertyPath) {
            $this->context->addViolationAt($this->propertyPath, $this->message, $this->parameters, $this->invalidValue, $this->plural, $this->code);

            return;
        }

        $this->context->addViolation($this->message, $this->parameters, $this->invalidValue, $this->plural, $this->code);
    }
}
