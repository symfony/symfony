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

use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Exception\TypeDefinitionException;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FormFactory implements FormFactoryInterface
{
    private static $requiredOptions = array(
        'data',
        'required',
        'max_length',
    );

    /**
     * Extensions
     * @var array An array of FormExtensionInterface
     */
    private $extensions = array();

    /**
     * All known types (cache)
     * @var array An array of FormTypeInterface
     */
    private $types = array();

    /**
     * The guesser chain
     * @var FormTypeGuesserChain
     */
    private $guesser;

    /**
     * Constructor.
     *
     * @param array $extensions An array of FormExtensionInterface
     *
     * @throws UnexpectedTypeException if any extension does not implement FormExtensionInterface
     */
    public function __construct(array $extensions)
    {
        foreach ($extensions as $extension) {
            if (!$extension instanceof FormExtensionInterface) {
                throw new UnexpectedTypeException($extension, 'Symfony\Component\Form\FormExtensionInterface');
            }
        }

        $this->extensions = $extensions;
    }

    /**
     * {@inheritdoc}
     */
    public function hasType($name)
    {
        if (isset($this->types[$name])) {
            return true;
        }

        try {
            $this->loadType($name);
        } catch (FormException $e) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function addType(FormTypeInterface $type)
    {
        $this->loadTypeExtensions($type);

        $this->validateFormTypeName($type);

        $this->types[$type->getName()] = $type;
    }

    /**
     * {@inheritdoc}
     */
    public function getType($name)
    {
        if (!is_string($name)) {
            throw new UnexpectedTypeException($name, 'string');
        }

        if (!isset($this->types[$name])) {
            $this->loadType($name);
        }

        return $this->types[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function create($type, $data = null, array $options = array(), FormBuilderInterface $parent = null)
    {
        return $this->createBuilder($type, $data, $options, $parent)->getForm();
    }

    /**
     * {@inheritdoc}
     */
    public function createNamed($name, $type, $data = null, array $options = array(), FormBuilderInterface $parent = null)
    {
        return $this->createNamedBuilder($name, $type, $data, $options, $parent)->getForm();
    }

    /**
     * {@inheritdoc}
     */
    public function createForProperty($class, $property, $data = null, array $options = array(), FormBuilderInterface $parent = null)
    {
        return $this->createBuilderForProperty($class, $property, $data, $options, $parent)->getForm();
    }

    /**
     * {@inheritdoc}
     */
    public function createBuilder($type, $data = null, array $options = array(), FormBuilderInterface $parent = null)
    {
        $name = is_object($type) ? $type->getName() : $type;

        return $this->createNamedBuilder($name, $type, $data, $options, $parent);
    }

    /**
     * {@inheritdoc}
     */
    public function createNamedBuilder($name, $type, $data = null, array $options = array(), FormBuilderInterface $parent = null)
    {
        if (!array_key_exists('data', $options)) {
            $options['data'] = $data;
        }

        $builder = null;
        $types = array();
        $optionsResolver = new OptionsResolver();

        // Bottom-up determination of the type hierarchy
        // Start with the actual type and look for the parent type
        // The complete hierarchy is saved in $types, the first entry being
        // the root and the last entry being the leaf (the concrete type)
        while (null !== $type) {
            if ($type instanceof FormTypeInterface) {
                if ($type->getName() == $type->getParent($options)) {
                    throw new FormException(sprintf('The form type name "%s" for class "%s" cannot be the same as the parent type.', $type->getName(), get_class($type)));
                }

                $this->addType($type);
            } elseif (is_string($type)) {
                $type = $this->getType($type);
            } else {
                throw new UnexpectedTypeException($type, 'string or Symfony\Component\Form\FormTypeInterface');
            }

            array_unshift($types, $type);

            // getParent() cannot see default options set by this type nor
            // default options set by parent types
            // As a result, the options always have to be checked for
            // existence with isset() before using them in this method.
            $type = $type->getParent($options);
        }

        // Top-down determination of the default options
        foreach ($types as $type) {
            // Merge the default options of all types to an array of default
            // options. Default options of children override default options
            // of parents.
            /* @var FormTypeInterface $type */
            $type->setDefaultOptions($optionsResolver);

            foreach ($type->getExtensions() as $typeExtension) {
                /* @var FormTypeExtensionInterface $typeExtension */
                $typeExtension->setDefaultOptions($optionsResolver);
            }
        }

        // Resolve concrete type
        $type = end($types);

        // Validate options required by the factory
        $diff = array();

        foreach (self::$requiredOptions as $requiredOption) {
            if (!$optionsResolver->isKnown($requiredOption)) {
                $diff[] = $requiredOption;
            }
        }

        if (count($diff) > 0) {
            throw new TypeDefinitionException(sprintf('Type "%s" should support the option(s) "%s"', $type->getName(), implode('", "', $diff)));
        }

        // Resolve options
        $options = $optionsResolver->resolve($options);

        for ($i = 0, $l = count($types); $i < $l && !$builder; ++$i) {
            $builder = $types[$i]->createBuilder($name, $this, $options);
        }

        if (!$builder) {
            throw new TypeDefinitionException(sprintf('Type "%s" or any of its parents should return a FormBuilderInterface instance from createBuilder()', $type->getName()));
        }

        $builder->setTypes($types);
        $builder->setCurrentLoadingType($type->getName());
        $builder->setParent($parent);

        foreach ($types as $type) {
            $type->buildForm($builder, $options);

            foreach ($type->getExtensions() as $typeExtension) {
                $typeExtension->buildForm($builder, $options);
            }
        }
        $builder->setCurrentLoadingType(null);

        return $builder;
    }

    /**
     * {@inheritdoc}
     */
    public function createBuilderForProperty($class, $property, $data = null, array $options = array(), FormBuilderInterface $parent = null)
    {
        if (!$this->guesser) {
            $this->loadGuesser();
        }

        $typeGuess = $this->guesser->guessType($class, $property);
        $maxLengthGuess = $this->guesser->guessMaxLength($class, $property);
        // Keep $minLengthGuess for BC until Symfony 2.3
        $minLengthGuess = $this->guesser->guessMinLength($class, $property);
        $requiredGuess = $this->guesser->guessRequired($class, $property);
        $patternGuess = $this->guesser->guessPattern($class, $property);

        $type = $typeGuess ? $typeGuess->getType() : 'text';

        $maxLength = $maxLengthGuess ? $maxLengthGuess->getValue() : null;
        $minLength = $minLengthGuess ? $minLengthGuess->getValue() : null;
        $pattern   = $patternGuess ? $patternGuess->getValue() : null;

        // overrides $minLength, if set
        if (null !== $pattern) {
            $options = array_merge(array('pattern' => $pattern), $options);
        }

        if (null !== $maxLength) {
            $options = array_merge(array('max_length' => $maxLength), $options);
        }

        if (null !== $minLength && $minLength > 0) {
            $options = array_merge(array('pattern' => '.{'.$minLength.','.$maxLength.'}'), $options);
        }

        if ($requiredGuess) {
            $options = array_merge(array('required' => $requiredGuess->getValue()), $options);
        }

        // user options may override guessed options
        if ($typeGuess) {
            $options = array_merge($typeGuess->getOptions(), $options);
        }

        return $this->createNamedBuilder($property, $type, $data, $options, $parent);
    }

    /**
     * Initializes the guesser chain.
     */
    private function loadGuesser()
    {
        $guessers = array();

        foreach ($this->extensions as $extension) {
            $guesser = $extension->getTypeGuesser();

            if ($guesser) {
                $guessers[] = $guesser;
            }
        }

        $this->guesser = new FormTypeGuesserChain($guessers);
    }

    /**
     * Loads a type.
     *
     * @param string $name The type name
     *
     * @throws FormException if the type is not provided by any registered extension
     */
    private function loadType($name)
    {
        $type = null;

        foreach ($this->extensions as $extension) {
            if ($extension->hasType($name)) {
                $type = $extension->getType($name);
                break;
            }
        }

        if (!$type) {
            throw new FormException(sprintf('Could not load type "%s"', $name));
        }

        $this->loadTypeExtensions($type);

        $this->validateFormTypeName($type);

        $this->types[$name] = $type;
    }

    /**
     * Loads the extensions for a given type.
     *
     * @param FormTypeInterface $type The type
     */
    private function loadTypeExtensions(FormTypeInterface $type)
    {
        $typeExtensions = array();

        foreach ($this->extensions as $extension) {
            $typeExtensions = array_merge(
                $typeExtensions,
                $extension->getTypeExtensions($type->getName())
            );
        }

        $type->setExtensions($typeExtensions);
    }

    private function validateFormTypeName(FormTypeInterface $type)
    {
        if (!preg_match('/^[a-z0-9_]*$/i', $type->getName())) {
            throw new FormException(sprintf('The "%s" form type name ("%s") is not valid. Names must only contain letters, numbers, and "_".', get_class($type), $type->getName()));
        }
    }
}
