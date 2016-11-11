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

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\MultipleStateMarking;
use Symfony\Component\Workflow\SingleStateMarking;

/**
 * PropertyAccessMarkingStore stores the marking into a property of the subject using
 * a PropertyAccessor.
 *
 * This store deals with both "single state" and "multiple state" Marking. It means a subject
 * can be in one or many states at the same time depending of the marking strategy.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Jules Pietri <jules@heahprod.com>
 */
class PropertyAccessMarkingStore extends MarkingStore
{
    private $property;
    private $propertyAccessor;

    /**
     * @param string                         $strategy
     * @param string                         $property
     * @param PropertyAccessorInterface|null $propertyAccessor
     * @param string                         $strategy         A Marking constant
     */
    public function __construct($property = 'marking', PropertyAccessorInterface $propertyAccessor = null, $strategy = Marking::STRATEGY_MULTIPLE_STATE)
    {
        parent::__construct($strategy);

        $this->property = $property;
        $this->propertyAccessor = $propertyAccessor ?: PropertyAccess::createPropertyAccessor();
    }

    /**
     * {@inheritdoc}
     */
    public function getSingleStateMarking($subject)
    {
        return new SingleStateMarking($this->propertyAccessor->getValue($subject, $this->property));
    }

    /**
     * {@inheritdoc}
     */
    public function getMultipleStateMarking($subject)
    {
        return new MultipleStateMarking($this->propertyAccessor->getValue($subject, $this->property) ?: array());
    }

    /**
     * {@inheritdoc}
     */
    public function setSingleStateMarking($subject, SingleStateMarking $marking)
    {
        $this->propertyAccessor->setValue($subject, $this->property, key($marking->getState()));
    }

    /**
     * {@inheritdoc}
     */
    public function setMultipleStateMarking($subject, MultipleStateMarking $marking)
    {
        $this->propertyAccessor->setValue($subject, $this->property, $marking->getState());
    }
}
