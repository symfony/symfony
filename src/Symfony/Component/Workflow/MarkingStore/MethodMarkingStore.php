<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\MarkingStore;

use Symfony\Component\Workflow\Exception\LogicException;
use Symfony\Component\Workflow\Marking;

/**
 * MethodMarkingStore stores the marking with a subject's public method
 * or public property.
 *
 * This store deals with a "single state" or "multiple state" marking.
 *
 * "single state" marking means a subject can be in one and only one state at
 * the same time. Use it with state machine. It uses a string to store the
 * marking.
 *
 * "multiple state" marking means a subject can be in many states at the same
 * time. Use it with workflow. It uses an array of strings to store the marking.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
final class MethodMarkingStore implements MarkingStoreInterface
{
    /** @var array<class-string, MarkingStoreMethod> */
    private array $getters = [];
    /** @var array<class-string, MarkingStoreMethod> */
    private array $setters = [];

    /**
     * @param string $property Used to determine the accessor methods or the property to call
     *                         `getMarking()` will use `$subject->getProperty()` or `$subject->$property`
     *                         `setMarking()` will use `$subject->setProperty(string|array|\BackedEnum $places, array $context = [])` or `$subject->$property = string|array|\BackedEnum $places`
     */
    public function __construct(
        private readonly bool $singleState = false,
        private readonly string $property = 'marking',
    ) {
    }

    public function getMarking(object $subject): Marking
    {
        $marking = null;
        try {
            $marking = ($this->getters[$subject::class] ??= $this->getGetter($subject))();
        } catch (\Error $e) {
            $unInitializedPropertyMessage = \sprintf('Typed property %s::$%s must not be accessed before initialization', get_debug_type($subject), $this->property);
            if ($e->getMessage() !== $unInitializedPropertyMessage) {
                throw $e;
            }
        }

        if (null === $marking) {
            return new Marking();
        }

        if ($this->singleState) {
            if ($marking instanceof \BackedEnum) {
                $marking = $marking->value;
            }

            $marking = [(string) $marking => 1];
        } elseif (!\is_array($marking)) {
            throw new LogicException(\sprintf('The marking stored in "%s::$%s" is not an array and the Workflow\'s Marking store is instantiated with $singleState=false.', get_debug_type($subject), $this->property));
        }

        return new Marking($marking);
    }

    public function setMarking(object $subject, Marking $marking, array $context = []): void
    {
        $marking = $marking->getPlaces();

        if ($this->singleState) {
            $marking = key($marking);
        }

        ($this->setters[$subject::class] ??= $this->getSetter($subject))($marking, $context);
    }

    private function getGetter(object $subject): callable
    {
        $property = $this->property;
        $method = 'get'.ucfirst($property);

        return match (self::getType($subject, $property, $method)) {
            MarkingStoreMethod::METHOD => $subject->{$method}(...),
            MarkingStoreMethod::PROPERTY => static fn () => $subject->{$property},
        };
    }

    private function getSetter(object $subject): callable
    {
        $property = $this->property;
        $method = 'set'.ucfirst($property);

        return match (self::getType($subject, $property, $method, $type)) {
            MarkingStoreMethod::METHOD => $type ? static fn ($marking, $context) => $subject->{$method}($type::from($marking), $context) : $subject->{$method}(...),
            MarkingStoreMethod::PROPERTY => $type ? static fn ($marking) => $subject->{$property} = $type::from($marking) : static fn ($marking) => $subject->{$property} = $marking,
        };
    }

    private static function getType(object $subject, string $property, string $method, ?string &$type = null): MarkingStoreMethod
    {
        try {
            if (method_exists($subject, $method) && ($r = new \ReflectionMethod($subject, $method))->isPublic()) {
                $type = 0 < $r->getNumberOfRequiredParameters() ? $r->getParameters()[0]->getType() : null;

                return MarkingStoreMethod::METHOD;
            }

            try {
                if (($r = new \ReflectionProperty($subject, $property))->isPublic()) {
                    $type = $r->getType();

                    return MarkingStoreMethod::PROPERTY;
                }
            } catch (\ReflectionException) {
            }

            throw new LogicException(\sprintf('Cannot store marking: class "%s" should have either a public method named "%s()" or a public property named "$%s"; none found.', get_debug_type($subject), $method, $property));
        } finally {
            $type = $type instanceof \ReflectionNamedType && is_subclass_of($type->getName(), \BackedEnum::class) ? $type->getName() : null;
        }
    }
}

/**
 * @internal
 */
enum MarkingStoreMethod
{
    case METHOD;
    case PROPERTY;
}
