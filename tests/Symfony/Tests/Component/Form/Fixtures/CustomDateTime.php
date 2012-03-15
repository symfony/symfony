<?php

namespace Symfony\Tests\Component\Form\Fixtures;

class CustomDateTime extends \DateTime
{
    private $timezone;

    public function __construct($date, $timezone = null)
    {
        parent::__construct($date, $timezone);
        $this->timezone = $timezone;
    }

    public function setTimeZone(\DateTimeZone $timezone)
    {
        $this->timezone = $timezone;
        return parent::setTimeZone($timezone);
    }

    public function getTimeZone()
    {
        return $this->timezone ?: parent::getTimezone();
    }
}
