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

class FormFactory implements FormFactoryInterface
{
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
     * Returns a type by name.
     *
     * This methods registers the type extensions from the form extensions.
     *
     * @param string|FormTypeInterface $name The name of the type or a type instance
     *
     * @return FormTypeInterface The type
     *
     * @throws FormException if the type can not be retrieved from any extension
     */
    public function getType($name)
    {
        $type = null;

        if ($name instanceof FormTypeInterface) {
            $type = $name;
            $name = $type->getName();
        }

        if (!isset($this->types[$name])) {
            if (!$type) {
                foreach ($this->extensions as $extension) {
                    if ($extension->hasType($name)) {
                        $type = $extension->getType($name);
                        break;
                    }
                }

                if (!$type) {
                    throw new FormException(sprintf('Could not load type "%s"', $name));
                }
            }

            $typeExtensions = array();

            foreach ($this->extensions as $extension) {
                $typeExtensions = array_merge(
                    $typeExtensions,
                    $extension->getTypeExtensions($name)
                );
            }

            $type->setExtensions($typeExtensions);

            $this->types[$name] = $type;
        }

        return $this->types[$name];
    }

    /**
     * Returns a form.
     *
     * @see createBuilder()
     *
     * @param string|FormTypeInterface  $type       The type of the form
     * @param mixed                     $data       The initial data
     * @param array                     $options    The options
     *
     * @return Form The form named after the type
     *
     * @throws FormException if any given option is not applicable to the given type
     */
    public function create($type, $data = null, array $options = array())
    {
        return $this->createBuilder($type, $data, $options)->getForm();
    }

    /**
     * Returns a form.
     *
     * @see createNamedBuilder()
     *
     * @param string|FormTypeInterface  $type       The type of the form
     * @param string                    $name       The name of the form
     * @param mixed                     $data       The initial data
     * @param array                     $options    The options
     *
     * @return Form The form
     *
     * @throws FormException if any given option is not applicable to the given type
     */
    public function createNamed($type, $name, $data = null, array $options = array())
    {
        return $this->createNamedBuilder($type, $name, $data, $options)->getForm();
    }

    /**
     * Returns a form for a property of a class.
     *
     * @see createBuilderForProperty()
     *
     * @param string $class     The fully qualified class name
     * @param string $property  The name of the property to guess for
     * @param mixed  $data      The initial data
     * @param array  $options   The options for the builder
     *
     * @return Form The form named after the property
     *
     * @throws FormException if any given option is not applicable to the form type
     */
    public function createForProperty($class, $property, $data = null, array $options = array())
    {
        return $this->createBuilderForProperty($class, $property, $data, $options)->getForm();
    }

    /**
     * Returns a form builder
     *
     * @param string|FormTypeInterface  $type       The type of the form
     * @param string                    $name       The name of the form
     * @param mixed                     $data       The initial data
     * @param array                     $options    The options
     *
     * @return FormBuilder The form builder
     *
     * @throws FormException if any given option is not applicable to the given type
     */
    public function createBuilder($type, $data = null, array $options = array())
    {
        $name = is_object($type) ? $type->getName() : $type;

        return $this->createNamedBuilder($type, $name, $data, $options);
    }

    /**
     * Returns a form builder.
     *
     * @param string|FormTypeInterface  $type       The type of the form
     * @param string                    $name       The name of the form
     * @param mixed                     $data       The initial data
     * @param array                     $options    The options
     *
     * @return FormBuilder The form builder
     *
     * @throws FormException if any given option is not applicable to the given type
     */
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
            $type = $this->getType($type);

            $defaultOptions = $type->getDefaultOptions($options);

            foreach ($type->getExtensions() as $typeExtension) {
                $defaultOptions = array_merge($defaultOptions, $typeExtension->getDefaultOptions($options));
            }

            $options = array_merge($defaultOptions, $options);
            $knownOptions = array_merge($knownOptions, array_keys($defaultOptions));
            array_unshift($types, $type);
            $type = $type->getParent($options);
        }

        $extraOptions = array_diff($passedOptions, $knownOptions);

        if (count($extraOptions) > 0) {
            throw new FormException(sprintf('The options "%s" do not exist', implode('", "', $extraOptions)));
        }

        for ($i = 0, $l = count($types); $i < $l && !$builder; ++$i) {
            $builder = $types[$i]->createBuilder($name, $this, $options);
        }

        // TODO check if instance exists

        $builder->setTypes($types);

        foreach ($types as $type) {
            $type->buildForm($builder, $options);

            foreach ($type->getExtensions() as $typeExtension) {
                $typeExtension->buildForm($builder, $options);
            }
        }

        return $builder;
    }

    /**
     * Returns a form builder for a property of a class.
     *
     * If any of the 'max_length', 'required' and type options can be guessed,
     * and are not provided in the options argument, the guessed value is used.
     *
     * @param string $class     The fully qualified class name
     * @param string $property  The name of the property to guess for
     * @param mixed  $data      The initial data
     * @param array  $options   The options for the builder
     *
     * @return FormBuilder The form builder named after the property
     *
     * @throws FormException if any given option is not applicable to the form type
     */
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
}
