<?php

namespace Symfony\Component\Messenger\Stamp;

use DateTime;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Stamp used to identify when a message should become available
 *
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
class AvailableAtStamp implements StampInterface
{
    /**
     * @var \DateTime
     */
    protected $availableAt;

    /**
     *
     * @param \DateTime $availableAt
     */
    public function __construct(DateTime $availableAt)
    {
        $this->availableAt = $availableAt;
    }

    /**
     * The date and time on which the message will be available
     *
     * @return \DateTime
     */
    public function getAvailableAt(): DateTime
    {
        return $this->availableAt;
    }
}
