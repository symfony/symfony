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

@trigger_error(sprintf('"%s" is deprecated since Symfony 4.3, use "%s" instead.', SingleStateMarkingStore::class, MethodMarkingStore::class), \E_USER_DEPRECATED);

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Workflow\Marking;

/**
 * SingleStateMarkingStore stores the marking into a property of the subject.
 *
 * This store deals with a "single state" Marking. It means a subject can be in
 * one and only one state at the same time.
 *
 * @deprecated since Symfony 4.3, use MethodMarkingStore instead.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class SingleStateMarkingStore implements MarkingStoreInterface
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
        $placeName = $this->propertyAccessor->getValue($subject, $this->property);

        if (null === $placeName) {
            return new Marking();
        }

        return new Marking([$placeName => 1]);
    }

    /**
     * {@inheritdoc}
     *
     * @param array $context Some context
     */
    public function setMarking($subject, Marking $marking/*, array $context = []*/)
    {
        $this->propertyAccessor->setValue($subject, $this->property, key($marking->getPlaces()));
    }

    /**
     * @return string
     */
    public function getProperty()
    {
        return $this->property;
    }
}
