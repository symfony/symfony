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
    public function create(string $type = 'Symfony\Component\Form\Extension\Core\Type\FormType', $data = null, array $options = [])
    {
        return $this->createBuilder($type, $data, $options)->getForm();
    }

    /**
     * {@inheritdoc}
     */
    public function createNamed(string $name, string $type = 'Symfony\Component\Form\Extension\Core\Type\FormType', $data = null, array $options = [])
    {
        return $this->createNamedBuilder($name, $type, $data, $options)->getForm();
    }

    /**
     * {@inheritdoc}
     */
    public function createForProperty(string $class, string $property, $data = null, array $options = [])
    {
        return $this->createBuilderForProperty($class, $property, $data, $options)->getForm();
    }

    /**
     * {@inheritdoc}
     */
    public function createBuilder(string $type = 'Symfony\Component\Form\Extension\Core\Type\FormType', $data = null, array $options = [])
    {
        return $this->createNamedBuilder($this->registry->getType($type)->getBlockPrefix(), $type, $data, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function createNamedBuilder(string $name, string $type = 'Symfony\Component\Form\Extension\Core\Type\FormType', $data = null, array $options = [])
    {
        if (null !== $data && !\array_key_exists('data', $options)) {
            $options['data'] = $data;
        }

        $type = $this->registry->getType($type);
        $dataClass = isset($options['data_class']) ? $options['data_class'] : null;

        if ($options['guess_options'] ?? false && null !== $dataClass && null !== $guesser = $this->registry->getTypeGuesser()) {
            $options = $options + $this->guessOptions($guesser, $dataClass, $name, $options);
        }

        $builder = $type->createBuilder($this, (string) $name, $options);

        // Explicitly call buildForm() in order to be able to override either
        // createBuilder() or buildForm() in the resolved form type
        $type->buildForm($builder, $builder->getOptions());

        return $builder;
    }

    /**
     * {@inheritdoc}
     */
    public function createBuilderForProperty(string $class, string $property, $data = null, array $options = [])
    {
        if (null === $guesser = $this->registry->getTypeGuesser()) {
            return $this->createNamedBuilder($property, 'Symfony\Component\Form\Extension\Core\Type\TextType', $data, $options);
        }

        $typeGuess = $guesser->guessType($class, $property);

        $type = $typeGuess ? $typeGuess->getType() : 'Symfony\Component\Form\Extension\Core\Type\TextType';

        $options = $this->guessOptions($guesser, $class, $property, $options);

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

    private function guessOptions(FormTypeGuesserInterface $guesser, string $class, string $property, array $options = []): array
    {
        $maxLengthGuess = $guesser->guessMaxLength($class, $property);
        $requiredGuess = $guesser->guessRequired($class, $property);
        $patternGuess = $guesser->guessPattern($class, $property);

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

        return $options;
    }
}
