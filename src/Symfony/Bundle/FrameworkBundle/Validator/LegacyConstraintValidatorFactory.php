<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Validator;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Like {@link ConstraintValidatorFactory}, but aware of services compatible
 * with the 2.4 API.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Kris Wallsmith <kris@symfony.com>
 *
 * @see ConstraintValidatorFactory
 */
class LegacyConstraintValidatorFactory implements ConstraintValidatorFactoryInterface
{
    const BASE_NAMESPACE = 'Symfony\\Component\\Validator\\Constraints';
    const FORM_BASE_NAMESPACE = 'Symfony\\Component\\Form\\Extension\\Validator\\Constraints';

    protected $container;
    protected $validators;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container  The service container
     * @param array              $validators An array of validators
     */
    public function __construct(ContainerInterface $container, array $validators = array())
    {
        $this->container = $container;
        $this->validators = $validators;
    }

    /**
     * Returns the validator for the supplied constraint.
     *
     * @param Constraint $constraint A constraint
     *
     * @return ConstraintValidatorInterface A validator for the supplied constraint
     *
     * @throws UnexpectedTypeException When the validator is not an instance of ConstraintValidatorInterface
     */
    public function getInstance(Constraint $constraint)
    {
        $name = $constraint->validatedBy();

        if (!isset($this->validators[$name])) {
            switch (get_class($constraint)) {
                case self::BASE_NAMESPACE.'\\All':
                    $name = self::BASE_NAMESPACE.'\\LegacyAllValidator';
                    break;
                case self::BASE_NAMESPACE.'\\Choice':
                    $name = self::BASE_NAMESPACE.'\\LegacyChoiceValidator';
                    break;
                case self::BASE_NAMESPACE.'\\Collection':
                    $name = self::BASE_NAMESPACE.'\\LegacyCollectionValidator';
                    break;
                case self::BASE_NAMESPACE.'\\Count':
                    $name = self::BASE_NAMESPACE.'\\LegacyCountValidator';
                    break;
                case self::BASE_NAMESPACE.'\\Length':
                    $name = self::BASE_NAMESPACE.'\\LegacyLengthValidator';
                    break;
                case self::FORM_BASE_NAMESPACE.'\\Form':
                    $name = self::FORM_BASE_NAMESPACE.'\\LegacyFormValidator';
                    break;
            }

            $this->validators[$name] = new $name();
        } elseif (is_string($this->validators[$name])) {
            $this->validators[$name] = $this->container->get($this->validators[$name]);
        }

        if (!$this->validators[$name] instanceof ConstraintValidatorInterface) {
            throw new UnexpectedTypeException($this->validators[$name], 'Symfony\Component\Validator\ConstraintValidatorInterface');
        }

        return $this->validators[$name];
    }
}
