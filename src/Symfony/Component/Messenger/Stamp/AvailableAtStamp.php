<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Stamp;

use DateTime;

/**
 * Stamp used to identify when a message should become available.
 *
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
class AvailableAtStamp implements StampInterface
{
    /**
     * @var \DateTime
     */
    protected $availableAt;

    public function __construct(DateTime $availableAt)
    {
        $this->availableAt = $availableAt;
    }

    /**
     * The date and time on which the message will be available.
     */
    public function getAvailableAt(): DateTime
    {
        return $this->availableAt;
    }
}
