<?php

namespace Symfony\Component\Form\Event;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Form\FormInterface;

class DataEvent extends Event
{
    private $form;

    protected $data;

    public function __construct(FormInterface $form, $data)
    {
        $this->form = $form;
        $this->data = $data;
    }

    public function getForm()
    {
        return $this->form;
    }

    public function getData()
    {
        return $this->data;
    }
}