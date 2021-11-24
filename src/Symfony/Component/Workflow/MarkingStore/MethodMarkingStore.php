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
use Symfony\Component\Workflow\Utils\PlaceEnumerationUtils;

/**
 * MethodMarkingStore stores the marking with a subject's method.
 *
 * This store deals with a "single state" or "multiple state" Marking.
 *
 * "single state" Marking means a subject can be in one and only one state at
 * the same time. Use it with state machine.
 *
 * "multiple state" Marking means a subject can be in many states at the same
 * time. Use it with workflow.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
final class MethodMarkingStore implements MarkingStoreInterface
{
    private bool $singleState;
    private string $property;

    /**
     * @param string $property Used to determine methods to call
     *                         The `getMarking` method will use `$subject->getProperty()`
     *                         The `setMarking` method will use `$subject->setProperty(string|array $places, array $context = array())`
     */
    public function __construct(bool $singleState = false, string $property = 'marking')
    {
        $this->singleState = $singleState;
        $this->property = $property;
    }

    /**
     * {@inheritdoc}
     */
    public function getMarking(object $subject): Marking
    {
        $method = 'get'.ucfirst($this->property);

        if (!method_exists($subject, $method)) {
            throw new LogicException(sprintf('The method "%s::%s()" does not exist.', get_debug_type($subject), $method));
        }

        $marking = $subject->{$method}();

        if (null === $marking) {
            return new Marking();
        }

        if ($this->singleState) {
            $markingRepresentation = [PlaceEnumerationUtils::getPlaceKey($marking) => 1];
        } else {
            $markingRepresentation = [];
            foreach ($marking as $key => $item) {
                // When using enumerations, as the enumeration case can't be used as an array key, the value is actually
                // stored in the item instead of the key.
                $markingRepresentation[PlaceEnumerationUtils::getPlaceKey($item instanceof \UnitEnum ? $item : $key)] = 1;
            }
        }

        return new Marking($markingRepresentation);
    }

    /**
     * {@inheritdoc}
     */
    public function setMarking(object $subject, Marking $marking, array $context = [])
    {
        $marking = $marking->getPlaces();

        if ($this->singleState) {
            $markingResult = PlaceEnumerationUtils::getTypedValue(key($marking));
        } else {
            $markingResult = [];
            foreach ($marking as $key => $item) {
                $value = PlaceEnumerationUtils::getTypedValue($key);
                if ($value instanceof \UnitEnum) {
                    // UnitEnum can't be used as array key, put it as a simple value without specific index.
                    $markingResult[] = $value;
                } else {
                    $markingResult[$value] = 1;
                }
            }
        }

        $method = 'set'.ucfirst($this->property);

        if (!method_exists($subject, $method)) {
            throw new LogicException(sprintf('The method "%s::%s()" does not exist.', get_debug_type($subject), $method));
        }

        $subject->{$method}($markingResult, $context);
    }
}
