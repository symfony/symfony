<?php

namespace Symfony\Component\Security\Core\Authentication\RememberMe;

use Symfony\Component\Security\Core\Exception\TokenNotFoundException;

/**
 * This class is used for testing purposes, and is not really suited for production.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class InMemoryTokenProvider implements TokenProviderInterface
{
    private $tokens = array();

    public function loadTokenBySeries($series)
    {
        if (!isset($this->tokens[$series])) {
            throw new TokenNotFoundException('No token found.');
        }

        return $this->tokens[$series];
    }

    public function updateToken($series, $tokenValue, \DateTime $lastUsed)
    {
        if (!isset($this->tokens[$series])) {
            throw new TokenNotFoundException('No token found.');
        }

        $token = new PersistentToken(
            $this->tokens[$series]->getClass(),
            $this->tokens[$series]->getUsername(),
            $series,
            $tokenValue,
            $lastUsed
        );
        $this->tokens[$series] = $token;
    }

    public function deleteTokenBySeries($series)
    {
        unset($this->tokens[$series]);
    }

    public function createNewToken(PersistentTokenInterface $token)
    {
        $this->tokens[$token->getSeries()] = $token;
    }
}