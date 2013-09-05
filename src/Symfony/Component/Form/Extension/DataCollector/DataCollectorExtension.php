<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\DataCollector;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\AbstractExtension;

/**
 * DataCollectorExtension for collecting Form Validation Failures.
 *
 * @author Robert Schönthal <robert.schoenthal@gmail.com>
 */
class DataCollectorExtension extends AbstractExtension
{
    /**
     * @var EventSubscriberInterface
     */
    private $eventSubscriber;

    public function __construct(EventSubscriberInterface $eventSubscriber)
    {
        $this->eventSubscriber = $eventSubscriber;
    }

    /**
     * {@inheritDoc}
     */
    protected function loadTypeExtensions()
    {
        return array(
            new Type\DataCollectorTypeExtension($this->eventSubscriber)
        );
    }
} 
