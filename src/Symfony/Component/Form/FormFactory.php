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

use Symfony\Component\Form\Exception\UnexpectedTypeException;

class FormFactory implements FormFactoryInterface
{
    /**
     * @var FormRegistryInterface
     */
    private $registry;

    /**
     * @var ResolvedFormTypeFactoryInterface
     */
    private $resolvedTypeFactory;

    protected $supportedAttributes = array(
        // rendered as input[type=text]
        'text' => array('maxlength', 'pattern'),
        'email' => array('maxlength', 'pattern'),
        'money' => array('maxlength', 'pattern'),
        'number' => array('maxlength', 'pattern'),
        'percent' => array('maxlength', 'pattern'),
        // rendered as input[type=number]
        'integer' => array('max', 'min'),
        // rendered as input[type=password]
        'password' => array('maxlength', 'pattern'),
        // rendered as input[type=search]
        'search' => array('maxlength', 'pattern'),
        // rendered as input[type=url]
        'url' => array('maxlength', 'pattern'),
        // rendered as textarea
        'textarea' => array('maxlength'),
    );

    public function __construct(FormRegistryInterface $registry, ResolvedFormTypeFactoryInterface $resolvedTypeFactory)
    {
        $this->registry = $registry;
        $this->resolvedTypeFactory = $resolvedTypeFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function create($type = 'form', $data = null, array $options = array())
    {
        return $this->createBuilder($type, $data, $options)->getForm();
    }

    /**
     * {@inheritdoc}
     */
    public function createNamed($name, $type = 'form', $data = null, array $options = array())
    {
        return $this->createNamedBuilder($name, $type, $data, $options)->getForm();
    }

    /**
     * {@inheritdoc}
     */
    public function createForProperty($class, $property, $data = null, array $options = array())
    {
        return $this->createBuilderForProperty($class, $property, $data, $options)->getForm();
    }

    /**
     * {@inheritdoc}
     */
    public function createBuilder($type = 'form', $data = null, array $options = array())
    {
        $name = $type instanceof FormTypeInterface || $type instanceof ResolvedFormTypeInterface
            ? $type->getName()
            : $type;

        return $this->createNamedBuilder($name, $type, $data, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function createNamedBuilder($name, $type = 'form', $data = null, array $options = array())
    {
        if (null !== $data && !array_key_exists('data', $options)) {
            $options['data'] = $data;
        }

        if ($type instanceof FormTypeInterface) {
            $type = $this->resolveType($type);
        } elseif (is_string($type)) {
            $type = $this->registry->getType($type);
        } elseif (!$type instanceof ResolvedFormTypeInterface) {
            throw new UnexpectedTypeException($type, 'string, Symfony\Component\Form\ResolvedFormTypeInterface or Symfony\Component\Form\FormTypeInterface');
        }

        $builder = $type->createBuilder($this, $name, $options);

        // Explicitly call buildForm() in order to be able to override either
        // createBuilder() or buildForm() in the resolved form type
        $type->buildForm($builder, $builder->getOptions());

        return $builder;
    }

    /**
     * {@inheritdoc}
     */
    public function createBuilderForProperty($class, $property, $data = null, array $options = array())
    {
        if (null === $guesser = $this->registry->getTypeGuesser()) {
            return $this->createNamedBuilder($property, 'text', $data, $options);
        }

        $typeGuess = $guesser->guessType($class, $property);
        $guessedAttributes = $guesser->guessAttributes($class, $property);

        $type = $typeGuess ? $typeGuess->getType() : 'text';

        if ($requiredGuess = $guesser->guessRequired($class, $property)) {
            $options = array_merge(array('required' => $requiredGuess->getValue()), $options);
        }

        // user options may override guessed options
        if ($typeGuess) {
            $options = array_merge($typeGuess->getOptions(), $options);
        }

        $filteredAttributes = array();

        foreach ($guessedAttributes as $key => $value) {
            if (null !== $value->getValue() && isset($this->supportedAttributes[$type]) && in_array($key, $this->supportedAttributes[$type])) {
                $filteredAttributes[$key] = $value->getValue();
            }
        }

        if (count($filteredAttributes)) {
            $options = array_merge(array(
                'attr' => $filteredAttributes
            ), $options);
        }

        return $this->createNamedBuilder($property, $type, $data, $options);
    }

    /**
     * Wraps a type into a ResolvedFormTypeInterface implementation and connects
     * it with its parent type.
     *
     * @param FormTypeInterface $type The type to resolve.
     *
     * @return ResolvedFormTypeInterface The resolved type.
     */
    private function resolveType(FormTypeInterface $type)
    {
        $parentType = $type->getParent();

        if ($parentType instanceof FormTypeInterface) {
            $parentType = $this->resolveType($parentType);
        } elseif (null !== $parentType) {
            $parentType = $this->registry->getType($parentType);
        }

        return $this->resolvedTypeFactory->createResolvedType(
            $type,
            // Type extensions are not supported for unregistered type instances,
            // i.e. type instances that are passed to the FormFactory directly,
            // nor for their parents, if getParent() also returns a type instance.
            array(),
            $parentType
        );
    }
}
