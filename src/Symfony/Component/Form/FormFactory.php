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
use Symfony\Component\Form\Util\StringUtil;

class FormFactory implements FormFactoryInterface
{
    private $registry;
    private $resolvedTypeFactory;

    public function __construct(FormRegistryInterface $registry, ResolvedFormTypeFactoryInterface $resolvedTypeFactory)
    {
        $this->registry = $registry;
        $this->resolvedTypeFactory = $resolvedTypeFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function create($type = 'Symfony\Component\Form\Extension\Core\Type\FormType', $data = null, array $options = array())
    {
        return $this->createBuilder($type, $data, $options)->getForm();
    }

    /**
     * {@inheritdoc}
     */
    public function createNamed($name, $type = 'Symfony\Component\Form\Extension\Core\Type\FormType', $data = null, array $options = array())
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
    public function createBuilder($type = 'Symfony\Component\Form\Extension\Core\Type\FormType', $data = null, array $options = array())
    {
        $name = null;

        if ($type instanceof ResolvedFormTypeInterface) {
            $typeObject = $type;
        } elseif ($type instanceof FormTypeInterface) {
            $typeObject = $type;
        } elseif (is_string($type)) {
            $typeObject = $this->registry->getType($type);
            $name = $type;
        } else {
            throw new UnexpectedTypeException($type, 'string, Symfony\Component\Form\ResolvedFormTypeInterface or Symfony\Component\Form\FormTypeInterface');
        }

        if (method_exists($typeObject, 'getBlockPrefix')) {
            // As of Symfony 3.0, the block prefix of the type is used as default name
            $name = $typeObject->getBlockPrefix();
        } else {
            // BC when there is no block prefix
            if (null === $name) {
                $name = $typeObject->getName();
            }
            if (false !== strpos($name, '\\')) {
                // FQCN
                $name = StringUtil::fqcnToBlockPrefix($name);
            }
        }

        return $this->createNamedBuilder($name, $type, $data, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function createNamedBuilder($name, $type = 'Symfony\Component\Form\Extension\Core\Type\FormType', $data = null, array $options = array())
    {
        if (null !== $data && !array_key_exists('data', $options)) {
            $options['data'] = $data;
        }

        if ($type instanceof FormTypeInterface) {
            @trigger_error(sprintf('Passing type instances to FormBuilder::add(), Form::add() or the FormFactory is deprecated since Symfony 2.8 and will not be supported in 3.0. Use the fully-qualified type class name instead (%s).', get_class($type)), E_USER_DEPRECATED);
            $type = $this->resolveType($type);
        } elseif (is_string($type)) {
            $type = $this->registry->getType($type);
        } elseif ($type instanceof ResolvedFormTypeInterface) {
            @trigger_error(sprintf('Passing type instances to FormBuilder::add(), Form::add() or the FormFactory is deprecated since Symfony 2.8 and will not be supported in 3.0. Use the fully-qualified type class name instead (%s).', get_class($type->getInnerType())), E_USER_DEPRECATED);
        } else {
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
            return $this->createNamedBuilder($property, 'Symfony\Component\Form\Extension\Core\Type\TextType', $data, $options);
        }

        $typeGuess = $guesser->guessType($class, $property);
        $maxLengthGuess = $guesser->guessMaxLength($class, $property);
        $requiredGuess = $guesser->guessRequired($class, $property);
        $patternGuess = $guesser->guessPattern($class, $property);

        $type = $typeGuess ? $typeGuess->getType() : 'Symfony\Component\Form\Extension\Core\Type\TextType';

        $maxLength = $maxLengthGuess ? $maxLengthGuess->getValue() : null;
        $pattern = $patternGuess ? $patternGuess->getValue() : null;

        if (null !== $pattern) {
            $options = array_replace_recursive(array('attr' => array('pattern' => $pattern)), $options);
        }

        if (null !== $maxLength) {
            $options = array_replace_recursive(array('attr' => array('maxlength' => $maxLength)), $options);
        }

        if ($requiredGuess) {
            $options = array_merge(array('required' => $requiredGuess->getValue()), $options);
        }

        // user options may override guessed options
        if ($typeGuess) {
            $attrs = array();
            $typeGuessOptions = $typeGuess->getOptions();
            if (isset($typeGuessOptions['attr']) && isset($options['attr'])) {
                $attrs = array('attr' => array_merge($typeGuessOptions['attr'], $options['attr']));
            }

            $options = array_merge($typeGuessOptions, $options, $attrs);
        }

        return $this->createNamedBuilder($property, $type, $data, $options);
    }

    /**
     * Wraps a type into a ResolvedFormTypeInterface implementation and connects
     * it with its parent type.
     *
     * @return ResolvedFormTypeInterface The resolved type
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
