<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\UserSession;

interface UserSessionRepositoryInterface
{
    public function remove(UserSessionInterface $userSession): void;

    public function save(UserSessionInterface $userSession): void;

    public function findOneById(string $sessionId): ?UserSessionInterface;

    public function removeExpired(): void;

    public function create(string $sessionId, string $data, int $maxLifetime, int $getTimestamp): UserSessionInterface;
}
