<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Workflow\MarkingStore;

use Symphony\Component\PropertyAccess\PropertyAccess;
use Symphony\Component\PropertyAccess\PropertyAccessorInterface;
use Symphony\Component\Workflow\Marking;

/**
 * MultipleStateMarkingStore stores the marking into a property of the
 * subject.
 *
 * This store deals with a "multiple state" Marking. It means a subject can be
 * in many states at the same time.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class MultipleStateMarkingStore implements MarkingStoreInterface
{
    private $property;
    private $propertyAccessor;

    public function __construct(string $property = 'marking', PropertyAccessorInterface $propertyAccessor = null)
    {
        $this->property = $property;
        $this->propertyAccessor = $propertyAccessor ?: PropertyAccess::createPropertyAccessor();
    }

    /**
     * {@inheritdoc}
     */
    public function getMarking($subject)
    {
        return new Marking($this->propertyAccessor->getValue($subject, $this->property) ?: array());
    }

    /**
     * {@inheritdoc}
     */
    public function setMarking($subject, Marking $marking)
    {
        $this->propertyAccessor->setValue($subject, $this->property, $marking->getPlaces());
    }

    /**
     * @return string
     */
    public function getProperty()
    {
        return $this->property;
    }
}
