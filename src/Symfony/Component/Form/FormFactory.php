<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\PercentType;
use Symfony\Component\Form\Extension\Core\Type\RangeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class FormFactory implements FormFactoryInterface
{
    private FormRegistryInterface $registry;

    public function __construct(FormRegistryInterface $registry)
    {
        $this->registry = $registry;
    }

    public function create(string $type = FormType::class, mixed $data = null, array $options = []): FormInterface
    {
        return $this->createBuilder($type, $data, $options)->getForm();
    }

    public function createNamed(string $name, string $type = FormType::class, mixed $data = null, array $options = []): FormInterface
    {
        return $this->createNamedBuilder($name, $type, $data, $options)->getForm();
    }

    public function createForProperty(string $class, string $property, mixed $data = null, array $options = []): FormInterface
    {
        return $this->createBuilderForProperty($class, $property, $data, $options)->getForm();
    }

    public function createBuilder(string $type = FormType::class, mixed $data = null, array $options = []): FormBuilderInterface
    {
        return $this->createNamedBuilder($this->registry->getType($type)->getBlockPrefix(), $type, $data, $options);
    }

    public function createNamedBuilder(string $name, string $type = FormType::class, mixed $data = null, array $options = []): FormBuilderInterface
    {
        if (null !== $data && !\array_key_exists('data', $options)) {
            $options['data'] = $data;
        }

        $type = $this->registry->getType($type);

        $builder = $type->createBuilder($this, $name, $options);

        // Explicitly call buildForm() in order to be able to override either
        // createBuilder() or buildForm() in the resolved form type
        $type->buildForm($builder, $builder->getOptions());

        return $builder;
    }

    public function createBuilderForProperty(string $class, string $property, mixed $data = null, array $options = []): FormBuilderInterface
    {
        if (null === $guesser = $this->registry->getTypeGuesser()) {
            return $this->createNamedBuilder($property, TextType::class, $data, $options);
        }

        $typeGuess = $guesser->guessType($class, $property);
        $maxLengthGuess = $guesser->guessMaxLength($class, $property);
        $requiredGuess = $guesser->guessRequired($class, $property);
        $patternGuess = $guesser->guessPattern($class, $property);

        $type = $typeGuess ? $typeGuess->getType() : TextType::class;

        // the "pattern" and "maxlength" attributes are valid on text inputs only
        if ($this->isTextInput($type)) {
            if (null !== $pattern = $patternGuess?->getValue()) {
                $options = array_replace_recursive(['attr' => ['pattern' => $pattern]], $options);
            }

            if (null !== $maxLength = $maxLengthGuess?->getValue()) {
                $options = array_replace_recursive(['attr' => ['maxlength' => $maxLength]], $options);
            }
        }

        if ($requiredGuess) {
            $options = array_merge(['required' => $requiredGuess->getValue()], $options);
        }

        // user options may override guessed options
        if ($typeGuess) {
            $attrs = [];
            $typeGuessOptions = $typeGuess->getOptions();
            if (isset($typeGuessOptions['attr']) && isset($options['attr'])) {
                $attrs = ['attr' => array_merge($typeGuessOptions['attr'], $options['attr'])];
            }

            $options = array_merge($typeGuessOptions, $options, $attrs);
        }

        return $this->createNamedBuilder($property, $type, $data, $options);
    }

    private function isTextInput(string $type): bool
    {
        $resolvedType = $this->registry->getType($type);

        do {
            $innerType = $resolvedType->getInnerType();

            // both descend from TextType but render their own input types
            if ($innerType instanceof RangeType || $innerType instanceof ColorType) {
                return false;
            }

            if ($innerType instanceof TextType || $innerType instanceof NumberType || $innerType instanceof MoneyType || $innerType instanceof PercentType) {
                return true;
            }
        } while ($resolvedType = $resolvedType->getParent());

        return false;
    }
}
