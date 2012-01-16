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

use Symfony\Component\Form\Util\FormUtil;

use Symfony\Component\Form\Exception\UnexpectedTypeException;

/**
 * A choice list that can store arbitrary scalar and object choices.
 *
 * Arrays as choices are not supported.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ComplexChoiceList extends ChoiceList
{
    /**
     * Creates a new complex choice list.
     *
     * @param array $choices The array of choices. Choices may also be given
     *                       as hierarchy of unlimited depth. Hierarchies are
     *                       created by creating nested arrays. The title of
     *                       the sub-hierarchy can be stored in the array
     *                       key pointing to the nested array.
     * @param array $labels  The array of labels. The structure of this array
     *                       should match the structure of $choices.
     * @param array $preferredChoices A flat array of choices that should be
     *                                presented to the user with priority.
     */
    public function __construct(array $choices, array $labels, array $preferredChoices = array())
    {
        parent::__construct($choices, $labels, $preferredChoices, self::GENERATE, self::GENERATE);
    }
}
