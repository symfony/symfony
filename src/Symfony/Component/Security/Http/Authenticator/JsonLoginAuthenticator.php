<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PropertyAccess\Exception\AccessException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PasswordUpgradeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Provides a stateless implementation of an authentication via
 * a JSON document composed of a username and a password.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @final
 */
class JsonLoginAuthenticator implements InteractiveAuthenticatorInterface
{
    private array $options;
    private HttpUtils $httpUtils;
    private UserProviderInterface $userProvider;
    private PropertyAccessorInterface $propertyAccessor;
    private ?AuthenticationSuccessHandlerInterface $successHandler;
    private ?AuthenticationFailureHandlerInterface $failureHandler;
    private ?TranslatorInterface $translator = null;

    public function __construct(HttpUtils $httpUtils, UserProviderInterface $userProvider, ?AuthenticationSuccessHandlerInterface $successHandler = null, ?AuthenticationFailureHandlerInterface $failureHandler = null, array $options = [], ?PropertyAccessorInterface $propertyAccessor = null)
    {
        $this->options = array_merge(['username_path' => 'username', 'password_path' => 'password'], $options);
        $this->httpUtils = $httpUtils;
        $this->successHandler = $successHandler;
        $this->failureHandler = $failureHandler;
        $this->userProvider = $userProvider;
        $this->propertyAccessor = $propertyAccessor ?: PropertyAccess::createPropertyAccessor();
    }

    public function supports(Request $request): ?bool
    {
        if (
            !str_contains($request->getRequestFormat() ?? '', 'json')
            && !str_contains($request->getContentTypeFormat() ?? '', 'json')
        ) {
            return false;
        }

        if (isset($this->options['check_path']) && !$this->httpUtils->checkRequestPath($request, $this->options['check_path'])) {
            return false;
        }

        return true;
    }

    public function authenticate(Request $request): Passport
    {
        try {
            $data = json_decode($request->getContent());
            if (!$data instanceof \stdClass) {
                throw new BadRequestHttpException('Invalid JSON.');
            }

            $credentials = $this->getCredentials($data);
        } catch (BadRequestHttpException $e) {
            $request->setRequestFormat('json');

            throw $e;
        }

        $userBadge = new UserBadge($credentials['username'], $this->userProvider->loadUserByIdentifier(...));
        $passport = new Passport($userBadge, new PasswordCredentials($credentials['password']), [new RememberMeBadge((array) $data)]);

        if ($this->userProvider instanceof PasswordUpgraderInterface) {
            $passport->addBadge(new PasswordUpgradeBadge($credentials['password'], $this->userProvider));
        }

        return $passport;
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        return new UsernamePasswordToken($passport->getUser(), $firewallName, $passport->getUser()->getRoles());
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if (null === $this->successHandler) {
            return null; // let the original request continue
        }

        return $this->successHandler->onAuthenticationSuccess($request, $token);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        if (null === $this->failureHandler) {
            if (null !== $this->translator) {
                $errorMessage = $this->translator->trans($exception->getMessageKey(), $exception->getMessageData(), 'security');
            } else {
                $errorMessage = strtr($exception->getMessageKey(), $exception->getMessageData());
            }

            return new JsonResponse(['error' => $errorMessage], JsonResponse::HTTP_UNAUTHORIZED);
        }

        return $this->failureHandler->onAuthenticationFailure($request, $exception);
    }

    public function isInteractive(): bool
    {
        return true;
    }

    public function setTranslator(TranslatorInterface $translator): void
    {
        $this->translator = $translator;
    }

    private function getCredentials(\stdClass $data): array
    {
        $credentials = [];
        try {
            $credentials['username'] = $this->propertyAccessor->getValue($data, $this->options['username_path']);

            if (!\is_string($credentials['username']) || '' === $credentials['username']) {
                throw new BadRequestHttpException(sprintf('The key "%s" must be a non-empty string.', $this->options['username_path']));
            }
        } catch (AccessException $e) {
            throw new BadRequestHttpException(sprintf('The key "%s" must be provided.', $this->options['username_path']), $e);
        }

        try {
            $credentials['password'] = $this->propertyAccessor->getValue($data, $this->options['password_path']);
            $this->propertyAccessor->setValue($data, $this->options['password_path'], null);

            if (!\is_string($credentials['password']) || '' === $credentials['password']) {
                throw new BadRequestHttpException(sprintf('The key "%s" must be a non-empty string.', $this->options['password_path']));
            }
        } catch (AccessException $e) {
            throw new BadRequestHttpException(sprintf('The key "%s" must be provided.', $this->options['password_path']), $e);
        }

        return $credentials;
    }
}
