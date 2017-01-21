<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authorization\Voter\Report;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * @author Maxime Perrimond <max.perrimond@gmail.com>
 */
interface VoteReportInterface
{
    /**
     * @return string
     */
    public function getMessage();

    /**
     * @return string
     */
    public function getAttribute();

    /**
     * @return mixed
     */
    public function getSubject();

    /**
     * @return TokenInterface
     */
    public function getToken();

    /**
     * @return VoterInterface
     */
    public function getVoter();
}
