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
final class VoteReport implements VoteReportInterface
{
    /**
     * @var string
     */
    private $message;

    /**
     * @var string
     */
    private $attribute;

    /**
     * @var mixed
     */
    private $subject;

    /**
     * @var TokenInterface
     */
    private $token;

    /**
     * @var VoterInterface
     */
    private $voter;

    /**
     * @param string         $message
     * @param VoterInterface $voter
     * @param string         $attribute
     * @param mixed          $subject
     * @param TokenInterface $token
     */
    public function __construct($message, VoterInterface $voter, $attribute, $subject, TokenInterface $token)
    {
        $this->message = $message;
        $this->attribute = $attribute;
        $this->subject = $subject;
        $this->voter = $voter;
        $this->token = $token;
    }

    /**
     * {@inheritdoc}
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttribute()
    {
        return $this->attribute;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * {@inheritdoc}
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * {@inheritdoc}
     */
    public function getVoter()
    {
        return $this->voter;
    }
}
