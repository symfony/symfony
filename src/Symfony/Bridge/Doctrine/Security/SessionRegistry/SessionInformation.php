<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Security\SessionRegistry;

use Symfony\Component\Security\Http\Session\SessionInformation as BaseSessionInformation;

/**
 * SessionInformation.
 *
 * Allows to persist SessionInformation using Doctrine DBAL.
 *
 * @author Stefan Paschke <stefan.paschke@gmail.com>
 */
class SessionInformation extends BaseSessionInformation
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
