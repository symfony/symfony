<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Exception;

/**
 * This exception is thrown if there where too many failed login attempts in
 * this session.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class SessionLockedException extends AuthenticationException
{
    private $threshold;

    /**
     * @param int $threshold in minutes
     */
    public function __construct(int $threshold)
    {
        $this->threshold = $threshold;
    }

    public function getMessageData(): array
    {
        return [
            '%minutes%' => $this->threshold,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getMessageKey(): string
    {
        return 'Too many failed login attempts, please try again in %minutes% minutes.';
    }

    /**
     * {@inheritdoc}
     */
    public function __serialize(): array
    {
        return [$this->threshold, parent::__serialize()];
    }

    /**
     * {@inheritdoc}
     */
    public function __unserialize(array $data): void
    {
        [$this->threshold, $parentData] = $data;
        $parentData = \is_array($parentData) ? $parentData : unserialize($parentData);
        parent::__unserialize($parentData);
    }
}
