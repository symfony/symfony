<?php

namespace Symfony\Component\Form\Event;

use Symfony\Component\EventDispatcher\Event;

class DataEvent extends Event
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function getData()
    {
        return $this->data;
    }
}