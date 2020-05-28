<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Dsn\Configuration;

trait UserPasswordTrait
{
    /**
     * @var array{
     *             user: string|null,
     *             password: string|null,
     *             }
     */
    private $authentication = ['user' => null, 'password' => null];

    /**
     * @return array
     */
    public function getAuthentication()
    {
        return $this->authentication;
    }

    private function setAuthentication(array $authentication): void
    {
        if (!empty($authentication)) {
            $this->authentication = $authentication;
        }
    }

    public function getUser(): ?string
    {
        return $this->authentication['user'] ?? null;
    }

    public function getPassword(): ?string
    {
        return $this->authentication['password'] ?? null;
    }

    private function getUserInfoString(): string
    {
        $user = $this->getUser() ?? '';
        $password = $this->getPassword() ?? '';
        $userInfo = $user.(empty($password) ? '' : ':'.$password).'@';
        if (\strlen($userInfo) <= 2) {
            $userInfo = '';
        }

        return $userInfo;
    }
}
