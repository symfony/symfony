<?php

/*
 * This file is part of the Symfony package.
*
* (c) Fabien Potencier <fabien@symfony.com>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Symfony\Component\Form\Extension\Core\ChoiceList;

use Symfony\Component\Form\Exception\StringCastException;
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\PropertyAccess\PropertyPath;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * A choice list for object choices.
 *
 * Supports generation of choice labels, choice groups and choice values
 * by calling getters of the object (or associated objects).
 *
 * <code>
 * $choices = array($user1, $user2);
 *
 * // call getName() to determine the choice labels
 * $choiceList = new ObjectChoiceList($choices, 'name');
 * </code>
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ObjectChoiceList extends ChoiceList
{
    /**
     * @var PropertyAccessorInterface
     */
    private $propertyAccessor;

    /**
     * The property path used to obtain the choice label.
     *
     * @var PropertyPath
     */
    private $labelPath;

    /**
     * The property path used for object grouping.
     *
     * @var PropertyPath
     */
    private $groupPath;

    /**
     * The property path used to obtain the choice value.
     *
     * @var PropertyPath
     */
    private $valuePath;

    /**
     * Creates a new object choice list.
     *
     * @param array|\Traversable        $choices          The array of choices. Choices may also be given
     *                                                    as hierarchy of unlimited depth by creating nested
     *                                                    arrays. The title of the sub-hierarchy can be
     *                                                    stored in the array key pointing to the nested
     *                                                    array. The topmost level of the hierarchy may also
     *                                                    be a \Traversable.
     * @param string                    $labelPath        A property path pointing to the property used
     *                                                    for the choice labels. The value is obtained
     *                                                    by calling the getter on the object. If the
     *                                                    path is NULL, the object's __toString() method
     *                                                    is used instead.
     * @param array                     $preferredChoices A flat array of choices that should be
     *                                                    presented to the user with priority.
     * @param string                    $groupPath        A property path pointing to the property used
     *                                                    to group the choices. Only allowed if
     *                                                    the choices are given as flat array.
     * @param string                    $valuePath        A property path pointing to the property used
     *                                                    for the choice values. If not given, integers
     *                                                    are generated instead.
     * @param PropertyAccessorInterface $propertyAccessor The reflection graph for reading property paths.
     */
    public function __construct($choices, $labelPath = null, array $preferredChoices = array(), $groupPath = null, $valuePath = null, PropertyAccessorInterface $propertyAccessor = null)
    {
        $this->propertyAccessor = $propertyAccessor ?: PropertyAccess::createPropertyAccessor();
        $this->labelPath = null !== $labelPath ? new PropertyPath($labelPath) : null;
        $this->groupPath = null !== $groupPath ? new PropertyPath($groupPath) : null;
        $this->valuePath = null !== $valuePath ? new PropertyPath($valuePath) : null;

        parent::__construct($choices, array(), $preferredChoices);
    }

    /**
     * Initializes the list with choices.
     *
     * Safe to be called multiple times. The list is cleared on every call.
     *
     * @param array|\Traversable $choices          The choices to write into the list.
     * @param array              $labels           Ignored.
     * @param array              $preferredChoices The choices to display with priority.
     *
     * @throws InvalidArgumentException When passing a hierarchy of choices and using
     *                                  the "groupPath" option at the same time.
     */
    protected function initialize($choices, array $labels, array $preferredChoices)
    {
        if (null !== $this->groupPath) {
            $groupedChoices = array();

            foreach ($choices as $i => $choice) {
                if (is_array($choice)) {
                    throw new InvalidArgumentException('You should pass a plain object array (without groups) when using the "groupPath" option.');
                }

                try {
                    $group = $this->propertyAccessor->getValue($choice, $this->groupPath);
                } catch (NoSuchPropertyException $e) {
                    // Don't group items whose group property does not exist
                    // see https://github.com/symfony/symfony/commit/d9b7abb7c7a0f28e0ce970afc5e305dce5dccddf
                    $group = null;
                }

                if (null === $group) {
                    $groupedChoices[$i] = $choice;
                } else {
                    $groupName = (string) $group;

                    if (!isset($groupedChoices[$groupName])) {
                        $groupedChoices[$groupName] = array();
                    }

                    $groupedChoices[$groupName][$i] = $choice;
                }
            }

            $choices = $groupedChoices;
        }

        $labels = array();

        $this->extractLabels($choices, $labels);

        parent::initialize($choices, $labels, $preferredChoices);
    }

    /**
     * {@inheritdoc}
     */
    public function getValuesForChoices(array $choices)
    {
        if (!$this->valuePath) {
            return parent::getValuesForChoices($choices);
        }

        // Use the value path to compare the choices
        $choices = $this->fixChoices($choices);
        $values = array();

        foreach ($choices as $i => $givenChoice) {
            // Ignore non-readable choices
            if (!is_object($givenChoice) && !is_array($givenChoice)) {
                continue;
            }

            $givenValue = (string) $this->propertyAccessor->getValue($givenChoice, $this->valuePath);

            foreach ($this->values as $value) {
                if ($value === $givenValue) {
                    $values[$i] = $value;
                    unset($choices[$i]);

                    if (0 === count($choices)) {
                        break 2;
                    }
                }
            }
        }

        return $values;
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated Deprecated since version 2.4, to be removed in 3.0.
     */
    public function getIndicesForChoices(array $choices)
    {
        if (!$this->valuePath) {
            return parent::getIndicesForChoices($choices);
        }

        // Use the value path to compare the choices
        $choices = $this->fixChoices($choices);
        $indices = array();

        foreach ($choices as $i => $givenChoice) {
            // Ignore non-readable choices
            if (!is_object($givenChoice) && !is_array($givenChoice)) {
                continue;
            }

            $givenValue = (string) $this->propertyAccessor->getValue($givenChoice, $this->valuePath);

            foreach ($this->values as $j => $value) {
                if ($value === $givenValue) {
                    $indices[$i] = $j;
                    unset($choices[$i]);

                    if (0 === count($choices)) {
                        break 2;
                    }
                }
            }
        }

        return $indices;
    }

    /**
     * Creates a new unique value for this choice.
     *
     * If a property path for the value was given at object creation,
     * the getter behind that path is now called to obtain a new value.
     * Otherwise a new integer is generated.
     *
     * @param mixed $choice The choice to create a value for
     *
     * @return int|string A unique value without character limitations.
     */
    protected function createValue($choice)
    {
        if ($this->valuePath) {
            return (string) $this->propertyAccessor->getValue($choice, $this->valuePath);
        }

        return parent::createValue($choice);
    }

    private function extractLabels($choices, array &$labels)
    {
        foreach ($choices as $i => $choice) {
            if (is_array($choice)) {
                $labels[$i] = array();
                $this->extractLabels($choice, $labels[$i]);
            } elseif ($this->labelPath) {
                $labels[$i] = $this->propertyAccessor->getValue($choice, $this->labelPath);
            } elseif (method_exists($choice, '__toString')) {
                $labels[$i] = (string) $choice;
            } else {
                throw new StringCastException(sprintf('A "__toString()" method was not found on the objects of type "%s" passed to the choice field. To read a custom getter instead, set the argument $labelPath to the desired property path.', get_class($choice)));
            }
        }
    }
}
