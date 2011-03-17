<?php

namespace Symfony\Component\Form\Event;

class FilterDataEvent extends DataEvent
{
    public function setData($data)
    {
        $this->data = $data;
    }
}