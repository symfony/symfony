<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\Security\Http\EventListener\RequiresPrivateTokenAttributeListener;
use Symfony\Component\Security\Http\PrivacyPass\BlindRsaTokenType;
use Symfony\Component\Security\Http\PrivacyPass\CacheSpentTokenStore;
use Symfony\Component\Security\Http\PrivacyPass\EmptyRedemptionContext;
use Symfony\Component\Security\Http\PrivacyPass\IssuerDirectory;
use Symfony\Component\Security\Http\PrivacyPass\PrivateTokenManager;
use Symfony\Component\Security\Http\PrivacyPass\PrivateTokenVerifier;
use Symfony\Component\Security\Http\PrivacyPass\SessionPrivateTokenManager;
use Symfony\Component\Security\Http\PrivacyPass\TimeWindowRedemptionContext;
use Symfony\Component\Security\Http\PrivacyPass\TokenChallengeFactory;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('security.private_token.attribute_listener', RequiresPrivateTokenAttributeListener::class)
            ->args([tagged_locator('security.private_token', 'name')])
            ->tag('kernel.event_subscriber')

        ->set('security.private_token.verifier', PrivateTokenVerifier::class)

        // the only token type an origin can verify on its own (RFC 9578, Section 8.2.2)
        ->set('security.private_token.token_type.blind_rsa', BlindRsaTokenType::class)

        ->set('security.private_token.manager', PrivateTokenManager::class)
            ->abstract()
            ->args([
                abstract_arg('challenge factory'),
                abstract_arg('spent token store'),
                service('security.private_token.verifier'),
                service('logger')->ignoreOnInvalid(),
            ])

        ->set('security.private_token.session_manager', SessionPrivateTokenManager::class)
            ->abstract()
            ->args([
                abstract_arg('decorated manager'),
                abstract_arg('lifetime'),
                abstract_arg('session key'),
                service('clock')->nullOnInvalid(),
            ])

        ->set('security.private_token.challenge_factory', TokenChallengeFactory::class)
            ->abstract()
            ->args([
                abstract_arg('issuer name'),
                service('security.private_token.token_type.blind_rsa'),
                abstract_arg('token keys'),
                abstract_arg('redemption context'),
                abstract_arg('origins'),
                abstract_arg('max age'),
                abstract_arg('grease percentage'),
            ])

        ->set('security.private_token.issuer_directory', IssuerDirectory::class)
            ->abstract()
            ->args([
                abstract_arg('http client'),
                abstract_arg('cache'),
                abstract_arg('issuer name'),
                service('security.private_token.token_type.blind_rsa'),
                abstract_arg('cache ttl'),
                service('clock')->nullOnInvalid(),
            ])

        ->set('security.private_token.redemption_context.time_window', TimeWindowRedemptionContext::class)
            ->abstract()
            ->args([
                abstract_arg('secret'),
                abstract_arg('window'),
                service('clock')->nullOnInvalid(),
            ])

        ->set('security.private_token.redemption_context.empty', EmptyRedemptionContext::class)

        ->set('security.private_token.spent_tokens', CacheSpentTokenStore::class)
            ->abstract()
            ->args([
                abstract_arg('cache'),
                abstract_arg('lifetime'),
            ])

        // the directories of the issuers and the tokens already spent
        ->set('cache.security.private_token')
            ->parent('cache.app')
            ->private()
            ->tag('cache.pool')
            ->tag('kernel.test_state', ['method' => 'clear'])
            ->tag('container.remove_if_missing', ['service' => 'cache.app'])
    ;
};
