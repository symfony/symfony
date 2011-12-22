<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Session\Dbal;

use Symfony\Component\Security\Http\Session\SessionInformation;

/**
 * DbalSessionInformation.
 *
 * Allows to persist SessionInformation using Doctrine DBAL.
 *
 * @author Stefan Paschke <stefan.paschke@gmail.com>
 */
class DbalSessionInformation extends SessionInformation
{
    public function __construct($sessionId, $username, \DateTime $lastRequest = null, \DateTime $expired = null)
    {
        parent::__construct($sessionId, $username);

        if (null !== $lastRequest) {
            $this->setLastRequest($lastRequest);
        }

        if (null !== $expired) {
            $this->setExpired($expired);
        }
    }

    public function getExpired()
    {
        return parent::getExpired();
    }
}
