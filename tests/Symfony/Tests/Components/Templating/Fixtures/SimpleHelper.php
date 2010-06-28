<?php

use Symfony\Components\Templating\Helper\Helper;

class SimpleHelper extends Helper
{
    protected $value = '';

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function __toString()
    {
        return $this->value;
    }

    public function getName()
    {
        return 'foo';
    }
}
