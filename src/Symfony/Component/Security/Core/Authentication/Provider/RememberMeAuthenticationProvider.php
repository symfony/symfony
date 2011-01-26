<?php
namespace Symfony\Component\Security\Core\Authentication\Provider;

use Symfony\Component\Security\Core\User\AccountCheckerInterface;
use Symfony\Component\Security\Core\User\AccountInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class RememberMeAuthenticationProvider implements AuthenticationProviderInterface
{
    protected $accountChecker;
    protected $key;
    protected $providerKey;

    public function __construct(AccountCheckerInterface $accountChecker, $key, $providerKey)
    {
        $this->accountChecker = $accountChecker;
        $this->key = $key;
        $this->providerKey = $providerKey;
    }

    public function authenticate(TokenInterface $token)
    {
        if (!$this->supports($token)) {
            return;
        }

        if ($this->key !== $token->getKey()) {
            throw new BadCredentialsException('The presented key does not match.');
        }

        $user = $token->getUser();
        $this->accountChecker->checkPreAuth($user);
        $this->accountChecker->checkPostAuth($user);
        $token->setAuthenticated(true);

        return $token;
    }

    public function supports(TokenInterface $token)
    {
        return $token instanceof RememberMeToken && $token->getProviderKey() === $this->providerKey;
    }
}