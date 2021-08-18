<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\RememberMeBundle\Security;

use Symfony\Component\Security\Core\Authentication\RememberMe\PersistentTokenInterface;
use Symfony\Component\Security\Core\Authentication\RememberMe\TokenProviderInterface;
use Symfony\Component\Security\Core\Exception\TokenNotFoundException;

class StaticTokenProvider implements TokenProviderInterface
{
    private static $db = [];
    private static $kernelClass;

    public function __construct($kernel)
    {
        // only reset the "internal db" for new tests
        if (self::$kernelClass !== \get_class($kernel)) {
            self::$kernelClass = \get_class($kernel);
            self::$db = [];
        }
    }

    public function loadTokenBySeries(string $series): PersistentTokenInterface
    {
        $token = self::$db[$series] ?? false;
        if (!$token) {
            throw new TokenNotFoundException();
        }

        return $token;
    }

    public function deleteTokenBySeries(string $series)
    {
        unset(self::$db[$series]);
    }

    public function updateToken(string $series, string $tokenValue, \DateTime $lastUsed)
    {
        $token = $this->loadTokenBySeries($series);
        $refl = new \ReflectionClass($token);
        $tokenValueProp = $refl->getProperty('tokenValue');
        $tokenValueProp->setAccessible(true);
        $tokenValueProp->setValue($token, $tokenValue);

        $lastUsedProp = $refl->getProperty('lastUsed');
        $lastUsedProp->setAccessible(true);
        $lastUsedProp->setValue($token, $lastUsed);

        self::$db[$series] = $token;
    }

    public function createNewToken(PersistentTokenInterface $token)
    {
        self::$db[$token->getSeries()] = $token;
    }
}
