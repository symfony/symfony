<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\Form\Type\FormTypeInterface;
use Symfony\Component\Form\Type\Loader\TypeLoaderInterface;
use Symfony\Component\Form\Type\Guesser\TypeGuesserInterface;
use Symfony\Component\Form\Type\Guesser\Guess;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Renderer\Loader\FormRendererFactoryLoaderInterface;

class FormFactory implements FormFactoryInterface
{
    private $typeLoader;

    private $rendererFactoryLoader;

    private $guessers = array();

    public function __construct(TypeLoaderInterface $typeLoader, FormRendererFactoryLoaderInterface $rendererFactoryLoader, array $guessers = array())
    {
        foreach ($guessers as $guesser) {
            if (!$guesser instanceof TypeGuesserInterface) {
                throw new UnexpectedTypeException($guesser, 'Symfony\Component\Form\Type\Guesser\TypeGuesserInterface');
            }
        }
        $this->typeLoader = $typeLoader;
        $this->guessers = $guessers;
        $this->rendererFactoryLoader = $rendererFactoryLoader;
    }

    public function createBuilder($type, $name = null, array $options = array())
    {
        // TODO $type can be FQN of a type class

        $builder = null;
        $types = array();
        $knownOptions = array();
        $passedOptions = array_keys($options);

        // TESTME
        if (null === $name) {
            $typeAsString = is_object($type) ? get_class($type) : $type;

            if (preg_match('/\w+$/', $typeAsString, $matches)) {
                $name = $matches[0];
            }
        }

        while (null !== $type) {
            // TODO check if type exists
            if (!$type instanceof FormTypeInterface) {
                $type = $this->typeLoader->getType($type);
            }

            array_unshift($types, $type);
            $defaultOptions = $type->getDefaultOptions($options);
            $options = array_merge($defaultOptions, $options);
            $knownOptions = array_merge($knownOptions, array_keys($defaultOptions));
            $type = $type->getParent($options);
        }

        $diff = array_diff($passedOptions, $knownOptions);

        if (count($diff) > 0) {
            throw new FormException(sprintf('The options "%s" do not exist', implode('", "', $diff)));
        }

        for ($i = 0, $l = count($types); $i < $l && !$builder; ++$i) {
            $builder = $types[$i]->createBuilder($options);
        }

        // TODO check if instance exists

        $builder->setName($name);
        $builder->setTypes($types);
        $builder->setFormFactory($this);

        foreach ($types as $type) {
            $type->configure($builder, $options);
        }

        return $builder;
    }

    public function create($type, $name = null, array $options = array())
    {
        return $this->createBuilder($type, $name, $options)->getForm();
    }

    public function createBuilderForProperty($class, $property, array $options = array())
    {
        // guess field class and options
        $typeGuess = $this->guess(function ($guesser) use ($class, $property) {
            return $guesser->guessType($class, $property);
        });

        // guess maximum length
        $maxLengthGuess = $this->guess(function ($guesser) use ($class, $property) {
            return $guesser->guessMaxLength($class, $property);
        });

        // guess whether field is required
        $requiredGuess = $this->guess(function ($guesser) use ($class, $property) {
            return $guesser->guessRequired($class, $property);
        });

        // construct field
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

        return $this->createBuilder($type, $property, $options);
    }

    /**
     * @inheritDoc
     */
    public function createForProperty($class, $property, array $options = array())
    {
        return $this->createBuilderForProperty($class, $property, $options)->getForm();
    }

    public function createRenderer(FormInterface $form, $name = null)
    {
        // TODO if $name === null, use default renderer

        return $this->rendererFactoryLoader->getRendererFactory($name)->create($form);
    }

    /**
     * Executes a closure for each guesser and returns the best guess from the
     * return values
     *
     * @param  \Closure $closure  The closure to execute. Accepts a guesser as
     *                            argument and should return a FieldFactoryGuess
     *                            instance
     * @return FieldFactoryGuess  The guess with the highest confidence
     */
    protected function guess(\Closure $closure)
    {
        $guesses = array();

        foreach ($this->guessers as $guesser) {
            if ($guess = $closure($guesser)) {
                $guesses[] = $guess;
            }
        }

        return Guess::getBestGuess($guesses);
    }
}
