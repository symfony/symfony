<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\DataMapper;

use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Util\InheritDataAwareIterator;

/**
 * Passes the data of forms to the constructor of the class they are mapped to.
 *
 * A promoted readonly property cannot be written once the object exists, so the data of the form mapped to it has to go through the constructor.
 *
 * @internal
 */
final class ConstructorMapper
{
    /**
     * @var array<class-string, array{parameters: array<string, \ReflectionParameter>, promoted: array<string, \ReflectionProperty>, readonly: array<string, \ReflectionProperty>, required: bool, replaceable: bool}>
     */
    private static array $constructors = [];

    /**
     * Creates an instance of the class from the data of the children of the form.
     *
     * The constructor is called without arguments when none of them is required or a promoted readonly property.
     *
     * @param class-string $class
     */
    public static function instantiate(string $class, FormInterface $form): object
    {
        $constructor = self::getConstructor($class);

        if (!$constructor['required'] && !$constructor['readonly']) {
            return new $class();
        }

        $forms = self::getMappedForms(new \RecursiveIteratorIterator(new InheritDataAwareIterator($form)));

        return new $class(...self::getArguments($class, $constructor, $forms, null));
    }

    /**
     * Creates the object again when the data of a form mapped to one of its promoted readonly properties changed.
     *
     * The object is created again only when all its properties are promoted, so that no state is lost.
     *
     * @param iterable<FormInterface> $forms
     *
     * @return iterable<FormInterface> The forms whose data remains to be written to the object
     */
    public static function mapFormsToObject(iterable $forms, object &$data): iterable
    {
        $constructor = self::getConstructor($data::class);

        if (!$constructor['readonly']) {
            return $forms;
        }

        $mappedForms = self::getMappedForms($forms);
        $readonlyForms = [];
        $changed = false;

        foreach ($constructor['readonly'] as $name => $property) {
            if (!$form = $mappedForms[$name] ?? null) {
                continue;
            }

            $readonlyForms[spl_object_id($form)] = true;

            if (!$changed && self::hasSubmittedArgument($data::class, $constructor['parameters'][$name], $form, $argument)) {
                $changed = !$property->isInitialized($data) || !self::isSame($argument, $property->getValue($data));
            }
        }

        if (!$readonlyForms) {
            return $forms;
        }

        if ($changed) {
            if (!$constructor['replaceable']) {
                return $forms;
            }

            $data = new ($data::class)(...self::getArguments($data::class, $constructor, $mappedForms, $data));
        }

        $remainingForms = [];

        foreach ($forms as $form) {
            if (!isset($readonlyForms[spl_object_id($form)])) {
                $remainingForms[] = $form;
            }
        }

        return $remainingForms;
    }

    /**
     * @param iterable<FormInterface> $forms
     *
     * @return array<string, FormInterface>
     */
    private static function getMappedForms(iterable $forms): array
    {
        $mappedForms = [];

        foreach ($forms as $form) {
            $config = $form->getConfig();

            if (!$config->getMapped() || null !== $config->getOption('setter')) {
                continue;
            }

            $propertyPath = $form->getPropertyPath();

            if (null !== $propertyPath && 1 === $propertyPath->getLength() && $propertyPath->isProperty(0)) {
                $mappedForms[$propertyPath->getElement(0)] = $form;
            }
        }

        return $mappedForms;
    }

    /**
     * @param array<string, FormInterface> $forms
     */
    private static function getArguments(string $class, array $constructor, array $forms, ?object $previous): array
    {
        $arguments = [];

        foreach ($constructor['parameters'] as $name => $parameter) {
            $form = $forms[$name] ?? null;

            if (self::hasSubmittedArgument($class, $parameter, $form, $argument)) {
                $arguments[] = $argument;
            } elseif ($previous && isset($constructor['promoted'][$name]) && $constructor['promoted'][$name]->isInitialized($previous)) {
                $arguments[] = $constructor['promoted'][$name]->getValue($previous);
            } elseif ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
            } elseif ($form) {
                throw new TransformationFailedException(\sprintf('No value was submitted for the "$%s" argument of "%s::__construct()".', $name, $class));
            } else {
                throw new LogicException(\sprintf('Cannot create "%s": no mapped field matches the "$%s" argument of its constructor.', $class, $name).($previous ? '' : ' Add such a field or set the "empty_data" option.'));
            }
        }

        return $arguments;
    }

    /**
     * Tells whether the data of a submitted form provides an argument to a parameter.
     *
     * An empty field leaves a non-nullable parameter to its default value.
     */
    private static function hasSubmittedArgument(string $class, \ReflectionParameter $parameter, ?FormInterface $form, mixed &$argument): bool
    {
        if (!$form?->isSubmitted() || !$form->isSynchronized() || $form->isDisabled()) {
            return false;
        }

        if (null !== $argument = $form->getData()) {
            return true;
        }

        if ($parameter->allowsNull()) {
            return true;
        }

        if (!$parameter->isDefaultValueAvailable()) {
            throw new TransformationFailedException(\sprintf('The "$%s" argument of "%s::__construct()" does not accept null.', $parameter->name, $class));
        }

        $argument = $parameter->getDefaultValue();

        return true;
    }

    private static function isSame(mixed $value, mixed $previous): bool
    {
        // date fields create a new instance on each submission, so dates are compared by value like PropertyPathAccessor does
        return $value === $previous || ($value instanceof \DateTimeInterface && $value == $previous);
    }

    /**
     * @param class-string $class
     */
    private static function getConstructor(string $class): array
    {
        if (isset(self::$constructors[$class])) {
            return self::$constructors[$class];
        }

        $constructor = [
            'parameters' => [],
            'promoted' => [],
            'readonly' => [],
            'required' => false,
            'replaceable' => true,
        ];
        $reflector = new \ReflectionClass($class);

        if (!($method = $reflector->getConstructor())?->isPublic()) {
            return self::$constructors[$class] = $constructor;
        }

        foreach ($method->getParameters() as $parameter) {
            if ($parameter->isVariadic()) {
                break;
            }

            $constructor['parameters'][$parameter->name] = $parameter;
            $constructor['required'] = $constructor['required'] || !$parameter->isOptional();

            if ($parameter->isPromoted()) {
                $property = $method->getDeclaringClass()->getProperty($parameter->name);
                $constructor['promoted'][$parameter->name] = $property;

                if ($property->isReadOnly()) {
                    $constructor['readonly'][$parameter->name] = $property;
                }
            }
        }

        for (; $reflector; $reflector = $reflector->getParentClass()) {
            foreach ($reflector->getProperties() as $property) {
                if ($property->class === $reflector->name && !$property->isStatic() && !$property->isVirtual() && !$property->isPromoted()) {
                    $constructor['replaceable'] = false;

                    break 2;
                }
            }
        }

        return self::$constructors[$class] = $constructor;
    }
}
