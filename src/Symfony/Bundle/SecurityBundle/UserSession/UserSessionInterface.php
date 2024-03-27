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

use Symfony\Component\Security\Core\User\UserInterface;

interface UserSessionInterface
{
    public function getId(): string;

    /**
     * @return string|resource
     */
    public function getData();

    public function setData(string $data): void;

    public function getLifetime(): int;

    public function setLifetime(int $lifetime): void;

    public function getTime(): int;

    public function getUser(): ?UserInterface;

    public function setUser(UserInterface $user): void;
}
