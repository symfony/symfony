<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\MongoDb\Stamp;

use MongoDB\Driver\Session;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

/**
 * Allows sending a message inside a MongoDB transaction, by passing the
 * session in which the transaction takes place.
 *
 * @author Alessandro Lai <alessandro.lai85@gmail.com>
 */
final class MongoDbSessionStamp implements NonSendableStampInterface
{
    public function __construct(private Session $session)
    {
    }

    public function getSession(): Session
    {
        return $this->session;
    }
}
