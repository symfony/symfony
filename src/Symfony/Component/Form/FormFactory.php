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
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\PercentType;
use Symfony\Component\Form\Extension\Core\Type\RangeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Flow\FormFlowBuilderInterface;
use Symfony\Component\Form\Flow\FormFlowInterface;
use Symfony\Component\Form\Flow\FormFlowTypeInterface;
use Symfony\Component\Form\Util\EmptyDataGuesser;
use Symfony\Component\PropertyAccess\PropertyPathInterface;

class FormFactory implements FormFactoryInterface
{
    private ?EmptyDataGuesser $emptyDataGuesser = null;

    public function __construct(
        private FormRegistryInterface $registry,
    ) {
    }

    /**
     * @return ($type is class-string<FormFlowTypeInterface> ? FormFlowInterface : FormInterface)
     */
    public function create(string $type = FormType::class, mixed $data = null, array $options = []): FormInterface
    {
        return $this->createBuilder($type, $data, $options)->getForm();
    }

    /**
     * @return ($type is class-string<FormFlowTypeInterface> ? FormFlowInterface : FormInterface)
     */
    public function createNamed(string $name, string $type = FormType::class, mixed $data = null, array $options = []): FormInterface
    {
        return $this->createNamedBuilder($name, $type, $data, $options)->getForm();
    }

    public function createForProperty(string $class, string $property, mixed $data = null, array $options = []): FormInterface
    {
        return $this->createBuilderForProperty($class, $property, $data, $options)->getForm();
    }

    /**
     * @return ($type is class-string<FormFlowTypeInterface> ? FormFlowBuilderInterface : FormBuilderInterface)
     */
    public function createBuilder(string $type = FormType::class, mixed $data = null, array $options = []): FormBuilderInterface
    {
        return $this->createNamedBuilder($this->registry->getType($type)->getBlockPrefix(), $type, $data, $options);
    }

    /**
     * @return ($type is class-string<FormFlowTypeInterface> ? FormFlowBuilderInterface : FormBuilderInterface)
     */
    public function createNamedBuilder(string $name, string $type = FormType::class, mixed $data = null, array $options = []): FormBuilderInterface
    {
        if (null !== $data && !\array_key_exists('data', $options)) {
            $options['data'] = $data;
        }

        $type = $this->registry->getType($type);

        $builder = $type->createBuilder($this, $name, $options);

        if ($builder instanceof FormFlowBuilderInterface) {
            $builder->setInitialOptions($options);
        }

        // Explicitly call buildForm() in order to be able to override either
        // createBuilder() or buildForm() in the resolved form type
        $type->buildForm($builder, $builder->getOptions());

        return $builder;
    }

    public function createBuilderForProperty(string $class, string $property, mixed $data = null, array $options = []): FormBuilderInterface
    {
        if (null === $guesser = $this->registry->getTypeGuesser()) {
            $options = $this->addEmptyDataGuess($class, $property, TextType::class, $options);

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

        $options = $this->addEmptyDataGuess($class, $property, $type, $options);

        return $this->createNamedBuilder($property, $type, $data, $options);
    }

    /**
     * Derives "empty_data" from the type of the mapped property when it refuses null.
     */
    private function addEmptyDataGuess(string $class, string $property, string $type, array $options): array
    {
        if (\array_key_exists('empty_data', $options) || !($options['mapped'] ?? true)) {
            return $options;
        }

        if (isset($options['property_path']) && null === $property = self::resolveWriteProperty($options['property_path'])) {
            return $options;
        }

        if (!$this->isScalarInput($type)) {
            return $options;
        }

        if (null !== $emptyData = ($this->emptyDataGuesser ??= new EmptyDataGuesser())->guess($class, $property)) {
            $options['empty_data'] = $emptyData;
        }

        return $options;
    }

    /**
     * Tells which property a "property_path" writes to, when it writes to a property of the mapped object at all.
     *
     * A property path is an arbitrary expression, so only a lone property segment names the write target.
     */
    private static function resolveWriteProperty(mixed $propertyPath): ?string
    {
        if ($propertyPath instanceof PropertyPathInterface) {
            return 1 === $propertyPath->getLength() && $propertyPath->isProperty(0) ? $propertyPath->getElement(0) : null;
        }

        return \is_string($propertyPath) && '' !== $propertyPath && false === strpbrk($propertyPath, '.[]?\\') ? $propertyPath : null;
    }

    /**
     * Tells whether the type maps a scalar to a single control, so that a scalar "empty_data" is meaningful for it.
     */
    private function isScalarInput(string $type): bool
    {
        foreach ($this->getInnerTypes($type) as $innerType) {
            if ($innerType instanceof TextType || $innerType instanceof IntegerType || $innerType instanceof NumberType || $innerType instanceof MoneyType || $innerType instanceof PercentType) {
                return true;
            }
        }

        return false;
    }

    private function isTextInput(string $type): bool
    {
        foreach ($this->getInnerTypes($type) as $innerType) {
            // both descend from TextType but render their own input types
            if ($innerType instanceof RangeType || $innerType instanceof ColorType) {
                return false;
            }

            if ($innerType instanceof TextType || $innerType instanceof NumberType || $innerType instanceof MoneyType || $innerType instanceof PercentType) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return iterable<FormTypeInterface>
     */
    private function getInnerTypes(string $type): iterable
    {
        $resolvedType = $this->registry->getType($type);

        do {
            yield $resolvedType->getInnerType();
        } while ($resolvedType = $resolvedType->getParent());
    }
}
