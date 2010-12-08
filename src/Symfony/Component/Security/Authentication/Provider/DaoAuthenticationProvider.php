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
 * DaoAuthenticationProvider uses a UserProviderInterface to retrieve the user
 * for a UsernamePasswordToken.
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
     * @param AccountCheckerInterface  $accountChecker  An AccountCheckerInterface instance
     * @param PasswordEncoderInterface $passwordEncoder A PasswordEncoderInterface instance
     */
    public function __construct(UserProviderInterface $userProvider, AccountCheckerInterface $accountChecker, PasswordEncoderInterface $passwordEncoder = null, $hideUserNotFoundExceptions = true)
    {
        parent::__construct($accountChecker, $hideUserNotFoundExceptions);

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
        $user = $token->getUser();
        if ($user instanceof AccountInterface) {
            if ($account->getPassword() !== $user->getPassword()) {
                throw new BadCredentialsException('The credentials were changed from another session.');
            }
        } else {
            if (!$presentedPassword = (string) $token->getCredentials()) {
                throw new BadCredentialsException('Bad credentials');
            }

            if (!$this->passwordEncoder->isPasswordValid($account->getPassword(), $presentedPassword, $account->getSalt())) {
                throw new BadCredentialsException('Bad credentials');
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function retrieveUser($username, UsernamePasswordToken $token)
    {
        $user = $token->getUser();
        if ($user instanceof AccountInterface) {
            return array($user, $token->getUserProviderName());
        }

        $result = null;
        try {
            $result = $this->userProvider->loadUserByUsername($username);
        } catch (UsernameNotFoundException $notFound) {
            throw $notFound;
        } catch (\Exception $repositoryProblem) {
            throw new AuthenticationServiceException($repositoryProblem->getMessage(), $token, 0, $repositoryProblem);
        }

        if (!is_array($result) || 2 !== count($result)) {
            throw new AuthenticationServiceException('User provider did not return an array, or array had invalid format.');
        }
        if (!$result[0] instanceof AccountInterface) {
            throw new AuthenticationServiceException('The user provider must return an AccountInterface object.');
        }
        if (empty($result[1])) {
            throw new AuthenticationServiceException('The user provider must return a non-empty user provider name.');
        }

        return $result;
    }
}
