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
use Symfony\Component\Security\Core\Authorization\Voter\Result\VoterResultInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * @author Maxime Perrimond <max.perrimond@gmail.com>
 */
interface VoteReportBuilderInterface
{
    /**
     * Adds the report to the current voter context.
     *
     * @param VoterInterface       $voter
     * @param VoterResultInterface $result
     * @param mixed                $subject
     * @param TokenInterface       $token
     */
    public function addReport(VoterInterface $voter, VoterResultInterface $result, $subject, TokenInterface $token);
}
