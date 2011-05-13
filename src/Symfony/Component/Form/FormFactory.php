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
use Symfony\Component\Form\Exception\CreationException;

class FormFactory implements FormFactoryInterface
{
    private static $requiredOptions = array(
        'data',
        'required',
        'max_length',
    );

    private $extensions = array();

    private $types = array();

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

    public function addType(FormTypeInterface $type)
    {
        $this->loadTypeExtensions($type);

        $this->types[$type->getName()] = $type;
    }

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

    public function create($type, $data = null, array $options = array())
    {
        return $this->createBuilder($type, $data, $options)->getForm();
    }

    public function createNamed($type, $name, $data = null, array $options = array())
    {
        return $this->createNamedBuilder($type, $name, $data, $options)->getForm();
    }

    /**
     * @inheritDoc
     */
    public function createForProperty($class, $property, $data = null, array $options = array())
    {
        return $this->createBuilderForProperty($class, $property, $data, $options)->getForm();
    }

    public function createBuilder($type, $data = null, array $options = array())
    {
        $name = is_object($type) ? $type->getName() : $type;

        return $this->createNamedBuilder($type, $name, $data, $options);
    }

    public function createNamedBuilder($type, $name, $data = null, array $options = array())
    {
        $builder = null;
        $types = array();
        $knownOptions = array();
        $passedOptions = array_keys($options);

        if (!array_key_exists('data', $options)) {
            $options['data'] = $data;
        }

        while (null !== $type) {
            if ($type instanceof FormTypeInterface) {
                $this->addType($type);
            } else {
                $type = $this->getType($type);
            }

            $defaultOptions = $type->getDefaultOptions($options);

            foreach ($type->getExtensions() as $typeExtension) {
                $defaultOptions = array_merge($defaultOptions, $typeExtension->getDefaultOptions($options));
            }

            $options = array_merge($defaultOptions, $options);
            $knownOptions = array_merge($knownOptions, array_keys($defaultOptions));
            array_unshift($types, $type);
            $type = $type->getParent($options);
        }

        $type = end($types);
        $diff = array_diff(self::$requiredOptions, $knownOptions);

        if (count($diff) > 0) {
            throw new TypeDefinitionException(sprintf('Type "%s" should support the option(s) "%s"', $type->getName(), implode('", "', $diff)));
        }

        $diff = array_diff($passedOptions, $knownOptions);

        if (count($diff) > 1) {
            throw new CreationException(sprintf('The options "%s" do not exist', implode('", "', $diff)));
        }

        if (count($diff) > 0) {
            throw new CreationException(sprintf('The option "%s" does not exist', $diff[0]));
        }

        for ($i = 0, $l = count($types); $i < $l && !$builder; ++$i) {
            $builder = $types[$i]->createBuilder($name, $this, $options);
        }

        if (!$builder) {
            throw new TypeDefinitionException(sprintf('Type "%s" or any of its parents should return a FormBuilder instance from createBuilder()', $type->getName()));
        }

        $builder->setTypes($types);

        foreach ($types as $type) {
            $type->buildForm($builder, $options);

            foreach ($type->getExtensions() as $typeExtension) {
                $typeExtension->buildForm($builder, $options);
            }
        }

        return $builder;
    }

    public function createBuilderForProperty($class, $property, $data = null, array $options = array())
    {
        if (!$this->guesser) {
            $this->loadGuesser();
        }

        $typeGuess = $this->guesser->guessType($class, $property);
        $maxLengthGuess = $this->guesser->guessMaxLength($class, $property);
        $requiredGuess = $this->guesser->guessRequired($class, $property);

        $type = $typeGuess ? $typeGuess->getType() : 'text';

        if ($maxLengthGuess) {
            $options = array_merge(array('max_length' => $maxLengthGuess->getValue()), $options);
        }

        if ($requiredGuess) {
            $options = array_merge(array('required' => $requiredGuess->getValue()), $options);
        }

        // user options may override guessed options
        if ($typeGuess) {
            $options = array_merge($typeGuess->getOptions(), $options);
        }

        return $this->createNamedBuilder($type, $property, $data, $options);
    }

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

        $this->types[$name] = $type;
    }

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
}
