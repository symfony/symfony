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
 * This class is used for testing purposes, and is not really suited for production.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @final since Symfony 6.4
 */
class InMemoryTokenProvider implements TokenProviderInterface
{
    private array $tokens = [];

    public function loadTokenBySeries(string $series): PersistentTokenInterface
    {
        if (!isset($this->tokens[$series])) {
            throw new TokenNotFoundException('No token found.');
        }

        return $this->tokens[$series];
    }

    /**
     * @param \DateTimeInterface $lastUsed Accepting only DateTime is deprecated since Symfony 6.4
     *
     * @return void
     */
    public function updateToken(string $series, #[\SensitiveParameter] string $tokenValue, \DateTime $lastUsed)
    {
        if (!isset($this->tokens[$series])) {
            throw new TokenNotFoundException('No token found.');
        }

        $token = new PersistentToken(
            $this->tokens[$series]->getClass(),
            $this->tokens[$series]->getUserIdentifier(),
            $series,
            $tokenValue,
            $lastUsed
        );
        $this->tokens[$series] = $token;
    }

    /**
     * @return void
     */
    public function deleteTokenBySeries(string $series)
    {
        unset($this->tokens[$series]);
    }

    /**
     * @return void
     */
    public function createNewToken(PersistentTokenInterface $token)
    {
        $this->tokens[$token->getSeries()] = $token;
    }
}
