<?php

namespace Symfony\Component\Security\Authentication\Provider;

use Symfony\Component\Security\User\UserProviderInterface;
use Symfony\Component\Security\User\AccountCheckerInterface;
use Symfony\Component\Security\User\AccountInterface;
use Symfony\Component\Security\Encoder\PasswordEncoderInterface;
use Symfony\Component\Security\Encoder\PlaintextPasswordEncoder;
use Symfony\Component\Security\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Exception\AuthenticationServiceException;
use Symfony\Component\Security\Exception\BadCredentialsException;
use Symfony\Component\Security\Authentication\Token\UsernamePasswordToken;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * DaoAuthenticationProvider uses a UserProviderInterface to retrieve the user for a UsernamePasswordToken.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class DaoAuthenticationProvider extends UserAuthenticationProvider
{
    protected $passwordEncoder;
    protected $userProvider;

    /**
     * Constructor.
     *
     * @param UserProviderInterface    $userProvider    A UserProviderInterface instance
     * @param PasswordEncoderInterface $passwordEncoder A PasswordEncoderInterface instance
     * @param AccountCheckerInterface  $accountChecker  An AccountCheckerInterface instance
     */
    public function __construct(UserProviderInterface $userProvider, AccountCheckerInterface $accountChecker, PasswordEncoderInterface $passwordEncoder = null)
    {
        parent::__construct($accountChecker);

        if (null === $passwordEncoder) {
            $passwordEncoder = new PlaintextPasswordEncoder();
        }
        $this->passwordEncoder = $passwordEncoder;
        $this->userProvider = $userProvider;
    }

    /**
     * {@inheritdoc}
     */
    protected function checkAuthentication(AccountInterface $account, UsernamePasswordToken $token)
    {
        if (null === $token->getCredentials()) {
            throw new BadCredentialsException('Bad credentials');
        }

        $presentedPassword = (string) $token->getCredentials();

        if (!$this->passwordEncoder->isPasswordValid($account->getPassword(), $presentedPassword, $account->getSalt())) {
            throw new BadCredentialsException('Bad credentials');
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function retrieveUser($username, UsernamePasswordToken $token)
    {
        $user = null;
        try {
            $user = $this->userProvider->loadUserByUsername($username);
        } catch (UsernameNotFoundException $notFound) {
            throw $notFound;
        } catch (\Exception $repositoryProblem) {
            throw new AuthenticationServiceException($repositoryProblem->getMessage(), $token, 0, $repositoryProblem);
        }

        if (null === $user) {
            throw new AuthenticationServiceException('UserProvider returned null.');
        }

        return $user;
    }
}
