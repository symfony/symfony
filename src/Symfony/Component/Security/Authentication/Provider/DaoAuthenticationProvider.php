<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Authentication\Provider;

use Symfony\Component\Security\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\User\UserProviderInterface;
use Symfony\Component\Security\User\AccountCheckerInterface;
use Symfony\Component\Security\User\AccountInterface;
use Symfony\Component\Security\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Exception\AuthenticationServiceException;
use Symfony\Component\Security\Exception\BadCredentialsException;
use Symfony\Component\Security\Authentication\Token\UsernamePasswordToken;

/**
 * DaoAuthenticationProvider uses a UserProviderInterface to retrieve the user
 * for a UsernamePasswordToken.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class DaoAuthenticationProvider extends UserAuthenticationProvider
{
    protected $encoderFactory;
    protected $userProvider;

    /**
     * Constructor.
     *
     * @param UserProviderInterface    $userProvider    A UserProviderInterface instance
     * @param AccountCheckerInterface  $accountChecker  An AccountCheckerInterface instance
     * @param EncoderFactoryInterface  $encoderFactory  A EncoderFactoryInterface instance
     */
    public function __construct(UserProviderInterface $userProvider, AccountCheckerInterface $accountChecker, EncoderFactoryInterface $encoderFactory, $hideUserNotFoundExceptions = true)
    {
        parent::__construct($accountChecker, $hideUserNotFoundExceptions);

        $this->encoderFactory = $encoderFactory;
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

            if (!$this->encoderFactory->getEncoder($account)->isPasswordValid($account->getPassword(), $presentedPassword, $account->getSalt())) {
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
            return $user;
        }

        try {
            $user = $this->userProvider->loadUserByUsername($username);

            if (!$user instanceof AccountInterface) {
                throw new AuthenticationServiceException('The user provider must return an AccountInterface object.');
            }

            return $user;
        } catch (UsernameNotFoundException $notFound) {
            throw $notFound;
        } catch (\Exception $repositoryProblem) {
            throw new AuthenticationServiceException($repositoryProblem->getMessage(), $token, 0, $repositoryProblem);
        }
    }
}
