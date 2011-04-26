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

use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

class FormFactory implements FormFactoryInterface
{
    private $extensions = array();

    private $types = array();

    private $guesser;

    public function __construct(array $extensions)
    {
        foreach ($extensions as $extension) {
            if (!$extension instanceof FormExtensionInterface) {
                throw new UnexpectedTypeException($extension, 'Symfony\Component\Form\FormExtensionInterface');
            }
        }

        $this->extensions = $extensions;
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

        $diff = array_diff($passedOptions, $knownOptions);

        if (count($diff) > 0) {
            throw new FormException(sprintf('The options "%s" do not exist', implode('", "', $diff)));
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
}
