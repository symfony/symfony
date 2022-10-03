<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests\Fixtures;

/**
 * @author Niels Keurentjes <niels.keurentjes@omines.com>
 */
class DateTimeConversion
{
    public \DateTime $publicDateTime;
    public \DateTimeImmutable $publicDateTimeImmutable;

    private \DateTime $dateTime;
    private \DateTimeImmutable $dateTimeImmutable;

    /**
     * @return \DateTime
     */
    public function getDateTime(): \DateTime
    {
        return $this->dateTime;
    }

    /**
     * @param \DateTime $dateTime
     */
    public function setDateTime(\DateTime $dateTime): void
    {
        $this->dateTime = $dateTime;
    }

    /**
     * @return \DateTimeImmutable
     */
    public function getDateTimeImmutable(): \DateTimeImmutable
    {
        return $this->dateTimeImmutable;
    }

    /**
     * @param \DateTimeImmutable $dateTimeImmutable
     */
    public function setDateTimeImmutable(\DateTimeImmutable $dateTimeImmutable): void
    {
        $this->dateTimeImmutable = $dateTimeImmutable;
    }
}
