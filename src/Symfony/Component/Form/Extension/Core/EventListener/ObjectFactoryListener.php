<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\EventListener;

use Symfony\Component\Form\Event\FilterDataEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Util\PropertyPath;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Based on scalar values, this class instanciates object and hydrate them.
 *
 * @author Marc Weistroff <marc.weistroff@sensio.com>
 */
class ObjectFactoryListener implements EventSubscriberInterface
{
    private $class;

    /**
     * __construct
     *
     * @param string $class FQCN class
     */
    public function __construct($class)
    {
        $this->class = $class;
    }

    static public function getSubscribedEvents()
    {
        return array(
            FormEvents::BIND_NORM_DATA => 'onBindNormData',
        );
    }

    public function onBindNormData(FilterDataEvent $event)
    {
        $data = $event->getData();

        foreach ($data as $k => $array) {
            if (!is_array($array)) {
                continue;
            }

            $object = new $this->class();
            foreach ($array as $key => $value) {
                $path = new PropertyPath($key);
                $path->setValue($object, $value);
            }

            $data[$k] = $object;
        }

        $event->setData($data);
    }
}

