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

use Symfony\Component\Form\Util\PropertyPath;
use Symfony\Component\Form\Exception\StringCastException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Exception\InvalidPropertyException;

/**
 * A choice list that can store object choices.
 *
 * Supports generation of choice labels, choice groups, choice values and
 * choice indices by introspecting the properties of the object (or
 * associated objects).
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ObjectChoiceList extends ChoiceList
{
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
     * The property path used to obtain the choice index.
     *
     * @var PropertyPath
     */
    private $indexPath;

    /**
     * Creates a new object choice list.
     *
     * @param array $choices The array of choices. Choices may also be given
     *                       as hierarchy of unlimited depth. Hierarchies are
     *                       created by creating nested arrays. The title of
     *                       the sub-hierarchy can be stored in the array
     *                       key pointing to the nested array.
     * @param string $labelPath A property path pointing to the property used
     *                          for the choice labels. The value is obtained
     *                          by calling the getter on the object. If the
     *                          path is NULL, the object's __toString() method
     *                          is used instead.
     * @param array $preferredChoices A flat array of choices that should be
     *                                presented to the user with priority.
     * @param string $groupPath A property path pointing to the property used
     *                          to group the choices. Only allowed if
     *                          the choices are given as flat array.
     * @param string $valuePath A property path pointing to the property used
     *                          for the choice values. If not given, integers
     *                          are generated instead.
     * @param string $indexPath A property path pointing to the property used
     *                          for the choice indices. If not given, integers
     *                          are generated instead.
     */
    public function __construct($choices, $labelPath = null, array $preferredChoices = array(), $groupPath = null, $valuePath = null, $indexPath = null)
    {
        $this->labelPath = $labelPath ? new PropertyPath($labelPath) : null;
        $this->groupPath = $groupPath ? new PropertyPath($groupPath) : null;
        $this->valuePath = $valuePath ? new PropertyPath($valuePath) : null;
        $this->indexPath = $indexPath ? new PropertyPath($indexPath) : null;

        parent::__construct($choices, array(), $preferredChoices, self::GENERATE, self::GENERATE);
    }

    /**
     * Initializes the list with choices.
     *
     * Safe to be called multiple times. The list is cleared on every call.
     *
     * @param array|\Traversable $choices The choices to write into the list.
     * @param array $labels Ignored.
     * @param array $preferredChoices The choices to display with priority.
     */
    protected function initialize($choices, array $labels, array $preferredChoices)
    {
        if (!is_array($choices) && !$choices instanceof \Traversable) {
            throw new UnexpectedTypeException($choices, 'array or \Traversable');
        }

        if ($this->groupPath !== null) {
            $groupedChoices = array();

            foreach ($choices as $i => $choice) {
                if (is_array($choice)) {
                    throw new \InvalidArgumentException('You should pass a plain object array (without groups, $code, $previous) when using the "groupPath" option');
                }

                try {
                    $group = $this->groupPath->getValue($choice);
                } catch (InvalidPropertyException $e) {
                    // Don't group items whose group property does not exist
                    // see https://github.com/symfony/symfony/commit/d9b7abb7c7a0f28e0ce970afc5e305dce5dccddf
                    $group = null;
                }

                if ($group === null) {
                    $groupedChoices[$i] = $choice;
                } else {
                    if (!isset($groupedChoices[$group])) {
                        $groupedChoices[$group] = array();
                    }

                    $groupedChoices[$group][$i] = $choice;
                }
            }

            $choices = $groupedChoices;
        }

        $labels = array();

        $this->extractLabels($choices, $labels);

        parent::initialize($choices, $labels, $preferredChoices);
    }

    /**
     * Creates a new unique index for this choice.
     *
     * If a property path for the index was given at object creation,
     * the getter behind that path is now called to obtain a new value.
     *
     * Otherwise a new integer is generated.
     *
     * @param mixed $choice The choice to create an index for
     * @return integer|string A unique index containing only ASCII letters,
     *                        digits and underscores.
     */
    protected function createIndex($choice)
    {
        if ($this->indexPath) {
            return $this->indexPath->getValue($choice);
        }

        return parent::createIndex($choice);
    }

    /**
     * Creates a new unique value for this choice.
     *
     * If a property path for the value was given at object creation,
     * the getter behind that path is now called to obtain a new value.
     *
     * Otherwise a new integer is generated.
     *
     * @param mixed $choice The choice to create a value for
     * @return integer|string A unique value without character limitations.
     */
    protected function createValue($choice)
    {
        if ($this->valuePath) {
            return $this->valuePath->getValue($choice);
        }

        return parent::createValue($choice);
    }

    private function extractLabels($choices, array &$labels)
    {
        foreach ($choices as $i => $choice) {
            if (is_array($choice) || $choice instanceof \Traversable) {
                $labels[$i] = array();
                $this->extractLabels($choice, $labels[$i]);
            } elseif ($this->labelPath) {
                $labels[$i] = $this->labelPath->getValue($choice);
            } elseif (method_exists($choice, '__toString')) {
                $labels[$i] = (string) $choice;
            } else {
                throw new StringCastException('Objects passed to the choice field must have a "__toString()" method defined. Alternatively you can set the $labelPath argument to choose the property used as label.');
            }
        }
    }
}
