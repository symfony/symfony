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

use Symfony\Component\Security\Http\AccessToken\ChainAccessTokenExtractor;
use Symfony\Component\Security\Http\AccessToken\FormEncodedBodyExtractor;
use Symfony\Component\Security\Http\AccessToken\HeaderAccessTokenExtractor;
use Symfony\Component\Security\Http\AccessToken\QueryAccessTokenExtractor;
use Symfony\Component\Security\Http\Authenticator\AccessTokenAuthenticator;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('security.access_token_extractor.header', HeaderAccessTokenExtractor::class)
        ->set('security.access_token_extractor.query_string', QueryAccessTokenExtractor::class)
        ->set('security.access_token_extractor.request_body', FormEncodedBodyExtractor::class)

        ->set('security.authenticator.access_token', AccessTokenAuthenticator::class)
            ->abstract()
            ->args([
                abstract_arg('access token handler'),
                abstract_arg('access token extractor'),
                null,
                null,
                null,
                null,
            ])

        ->set('security.authenticator.access_token.chain_extractor', ChainAccessTokenExtractor::class)
            ->abstract()
            ->args([
                abstract_arg('access token extractors'),
            ])
    ;
};
