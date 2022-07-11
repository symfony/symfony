<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authentication\RememberMe;

use Symfony\Component\Security\Core\Exception\TokenNotFoundException;

/**
 * Interface for TokenProviders.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface TokenProviderInterface
{
    /**
     * Loads the active token for the given series.
     *
     * @return PersistentTokenInterface
     *
     * @throws TokenNotFoundException if the token is not found
     */
    public function loadTokenBySeries(string $series);

    /**
     * Deletes all tokens belonging to series.
     */
    public function deleteTokenBySeries(string $series);

    /**
     * Updates the token according to this data.
     *
     * @throws TokenNotFoundException if the token is not found
     */
    public function updateToken(string $series, #[\SensitiveParameter] string $tokenValue, \DateTime $lastUsed);

    /**
     * Creates a new token.
     */
    public function createNewToken(PersistentTokenInterface $token);
}
