<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler\RegisterEntryPointPass;
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Component\DependencyInjection\Argument\AbstractArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Authentication\AuthenticatorManager;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Firewall\ExceptionListener;

class RegisterEntryPointsPassTest extends TestCase
{
    public function testProcessResolvesChildDefinitionsClass()
    {
        $container = new ContainerBuilder();

        $container->setParameter('security.firewalls', ['main']);
        $container->setParameter('security.main._indexed_authenticators', ['custom' => 'security.authenticator.custom_authenticator.main']);

        $container->register('security.authenticator.manager.main', AuthenticatorManager::class);
        $container->register('security.exception_listener.main', ExceptionListener::class)->setArguments([
            new AbstractArgument(),
            new AbstractArgument(),
            new AbstractArgument(),
            new AbstractArgument(),
            null, // entry point
        ]);
        $config = $container->register('security.firewall.map.config.main', FirewallConfig::class);
        $config->setArguments([
            new AbstractArgument(),
            new AbstractArgument(),
            new AbstractArgument(),
            new AbstractArgument(),
            new AbstractArgument(),
            new AbstractArgument(),
            new AbstractArgument(),
            null, // entry point,
        ]);

        $container->register('custom_authenticator', CustomAuthenticator::class)
            ->setAbstract(true);

        $container->setDefinition('security.authenticator.custom_authenticator.main', new ChildDefinition('custom_authenticator'));

        (new RegisterEntryPointPass())->process($container);

        $this->assertSame('security.authenticator.custom_authenticator.main', $config->getArgument(7));
    }
}

class CustomAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function supports(Request $request): ?bool
    {
        return false;
    }

    public function authenticate(Request $request): Passport
    {
        throw new BadCredentialsException();
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'error' => $exception->getMessageKey(),
        ], JsonResponse::HTTP_FORBIDDEN);
    }

    public function start(Request $request, AuthenticationException $authException = null): Response
    {
    }
}
