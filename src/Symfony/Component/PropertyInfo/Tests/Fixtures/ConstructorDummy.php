<?php

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

/**
 * @author Dmitrii Poddubnyi <dpoddubny@gmail.com>
 */
class ConstructorDummy
{
    /** @var string */
    private $timezone;

    /** @var \DateTimeInterface */
    private $date;

    /** @var int */
    private $dateTime;

    /**
     * @param \DateTimeZone      $timezone
     * @param int                $date       Timestamp
     * @param \DateTimeInterface $dateObject
     */
    public function __construct(\DateTimeZone $timezone, $date, $dateObject, \DateTime $dateTime)
    {
        $this->timezone = $timezone->getName();
        $this->date = \DateTime::createFromFormat('U', $date);
        $this->dateTime = $dateTime->getTimestamp();
    }
}
