<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Event;

use Symfony\Component\EventDispatcher\Event as BaseEvent;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Event extends BaseEvent
{
    private $object;
    private $state;
    private $attributes;

    public function __construct($object, $state, array $attributes = array())
    {
        $this->object = $object;
        $this->state = $state;
        $this->attributes = $attributes;
    }

    public function getState()
    {
        return $this->state;
    }

    public function getObject()
    {
        return $this->object;
    }

    public function getAttribute($key)
    {
        return isset($this->attributes[$key]) ? $this->attributes[$key] : null;
    }

    public function hastAttribute($key)
    {
        return isset($this->attributes[$key]);
    }
}
