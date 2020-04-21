<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator\Passport\Badge;

use Symfony\Component\Security\Core\Exception\LogicException;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * Adds automatic password migration, if enabled and required in the password encoder.
 *
 * @see PasswordUpgraderInterface
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @final
 * @experimental in 5.1
 */
class PasswordUpgradeBadge implements BadgeInterface
{
    private $plaintextPassword;
    private $passwordUpgrader;

    /**
     * @param string                    $plaintextPassword The presented password, used in the rehash
     * @param PasswordUpgraderInterface $passwordUpgrader  The password upgrader, usually the UserProvider
     */
    public function __construct(string $plaintextPassword, PasswordUpgraderInterface $passwordUpgrader)
    {
        $this->plaintextPassword = $plaintextPassword;
        $this->passwordUpgrader = $passwordUpgrader;
    }

    public function getAndErasePlaintextPassword(): string
    {
        $password = $this->plaintextPassword;
        if (null === $password) {
            throw new LogicException('The password is erased as another listener already used this badge.');
        }

        $this->plaintextPassword = null;

        return $password;
    }

    public function getPasswordUpgrader(): PasswordUpgraderInterface
    {
        return $this->passwordUpgrader;
    }

    public function isResolved(): bool
    {
        return true;
    }
}
