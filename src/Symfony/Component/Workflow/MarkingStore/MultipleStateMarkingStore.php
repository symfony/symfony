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

@trigger_error(sprintf('"%s" is deprecated since Symfony 4.3, use "%s" instead.', MultipleStateMarkingStore::class, MethodMarkingStore::class), \E_USER_DEPRECATED);

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Workflow\Marking;

/**
 * MultipleStateMarkingStore stores the marking into a property of the
 * subject.
 *
 * This store deals with a "multiple state" Marking. It means a subject can be
 * in many states at the same time.
 *
 * @deprecated since Symfony 4.3, use MethodMarkingStore instead.
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
        return new Marking($this->propertyAccessor->getValue($subject, $this->property) ?: []);
    }

    /**
     * {@inheritdoc}
     *
     * @param array $context Some context
     */
    public function setMarking($subject, Marking $marking/* , array $context = [] */)
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
