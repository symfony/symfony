<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\OAuth2\ClientAuthentication;

/**
 * Authenticates the client with the certificate it presents in the TLS handshake.
 *
 * This is the mutual-TLS client authentication of RFC 8705, Section 2, the only method
 * putting no credential in the request at all: the client proves it holds the private key
 * of a certificate the provider knows, in the handshake that carries the request, and the
 * "client_id" of RFC 6749, Section 2.3.1 names the registration that certificate is then
 * checked against. What it is checked against tells the two methods built on this apart,
 * {@see TlsClientAuth} matching a subject a certificate authority vouches for,
 * {@see SelfSignedTlsClientAuth} a public key the client registered itself.
 *
 * Only the transport of the request is touched, so what a client authenticated this way
 * sends to the provider is what it would send with no authentication at all. The provider
 * expects those requests at the endpoints it publishes under "mtls_endpoint_aliases"
 * (Section 5) whenever its ordinary ones ask for no client certificate, which
 * {@see \Symfony\Component\Security\Http\Oidc\OidcDiscovery::getSecureEndpoint()} resolves.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc8705#section-2 Mutual-TLS client authentication
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @internal
 */
abstract class AbstractTlsClientAuth implements ClientAuthenticationInterface
{
    /**
     * @param string      $certificate The path to the PEM file holding the client certificate, and its
     *                                 private key when both are in the same file
     * @param string|null $key         The path to the PEM file holding the private key, or null when the
     *                                 certificate file holds it
     * @param string|null $passphrase  The passphrase the private key is encrypted with, or null when it
     *                                 is not encrypted
     */
    public function __construct(
        private readonly string $certificate,
        private readonly ?string $key = null,
        #[\SensitiveParameter] private readonly ?string $passphrase = null,
    ) {
        if ('' === $certificate) {
            throw new \InvalidArgumentException(\sprintf('The certificate of the "%s" OAuth2 client authentication cannot be empty: it is the only credential the method holds.', $this->getMethod()));
        }

        if ('' === $key) {
            throw new \InvalidArgumentException(\sprintf('The private key of the "%s" OAuth2 client authentication cannot be empty. Pass null to read it from the certificate file, which a PEM file may hold both of.', $this->getMethod()));
        }
    }

    /**
     * Presents the client certificate on the request, and leaves its body untouched.
     *
     * The certificate is sent on every request made with these options, the handshake
     * happening before the provider gets to read any of them.
     */
    final public function authenticate(string $clientId, string $tokenEndpoint, array $options): array
    {
        $options['local_cert'] = $this->certificate;

        if (null !== $this->key) {
            $options['local_pk'] = $this->key;
        }

        if (null !== $this->passphrase) {
            $options['passphrase'] = $this->passphrase;
        }

        return $options;
    }
}
