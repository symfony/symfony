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

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class FormFactory implements FormFactoryInterface
{
    private $registry;

    public function __construct(FormRegistryInterface $registry)
    {
        $this->registry = $registry;
    }

    /**
     * {@inheritdoc}
     */
    public function create(string $type = FormType::class, mixed $data = null, array $options = []): FormInterface
    {
        return $this->createBuilder($type, $data, $options)->getForm();
    }

    /**
     * {@inheritdoc}
     */
    public function createNamed(string $name, string $type = FormType::class, mixed $data = null, array $options = []): FormInterface
    {
        return $this->createNamedBuilder($name, $type, $data, $options)->getForm();
    }

    /**
     * {@inheritdoc}
     */
    public function createForProperty(string $class, string $property, mixed $data = null, array $options = []): FormInterface
    {
        return $this->createBuilderForProperty($class, $property, $data, $options)->getForm();
    }

    /**
     * {@inheritdoc}
     */
    public function createBuilder(string $type = FormType::class, mixed $data = null, array $options = []): FormBuilderInterface
    {
        return $this->createNamedBuilder($this->registry->getType($type)->getBlockPrefix(), $type, $data, $options);
    }

    /**
     * {@inheritdoc}
     */
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

    /**
     * {@inheritdoc}
     */
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

        $maxLength = $maxLengthGuess ? $maxLengthGuess->getValue() : null;
        $pattern = $patternGuess ? $patternGuess->getValue() : null;

        if (null !== $pattern) {
            $options = array_replace_recursive(['attr' => ['pattern' => $pattern]], $options);
        }

        if (null !== $maxLength) {
            $options = array_replace_recursive(['attr' => ['maxlength' => $maxLength]], $options);
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
}
