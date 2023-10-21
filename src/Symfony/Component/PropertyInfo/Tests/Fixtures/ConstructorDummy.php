<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
     * @param mixed              $mixed
     */
    public function __construct(\DateTimeZone $timezone, $date, $dateObject, \DateTimeImmutable $dateTime, $mixed)
    {
        $this->timezone = $timezone->getName();
        $this->date = \DateTimeImmutable::createFromFormat('U', $date);
        $this->dateTime = $dateTime->getTimestamp();
    }
}
