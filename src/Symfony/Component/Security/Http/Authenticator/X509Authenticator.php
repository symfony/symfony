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

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * This authenticator authenticates pre-authenticated (by the
 * webserver) X.509 certificates.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class X509Authenticator extends AbstractPreAuthenticatedAuthenticator
{
    private string $userKey;
    private string $credentialsKey;
    private string $credentialUserIdentifier;

    public function __construct(UserProviderInterface $userProvider, TokenStorageInterface $tokenStorage, string $firewallName, string $userKey = 'SSL_CLIENT_S_DN_Email', string $credentialsKey = 'SSL_CLIENT_S_DN', ?LoggerInterface $logger = null, string $credentialUserIdentifier = 'emailAddress')
    {
        parent::__construct($userProvider, $tokenStorage, $firewallName, $logger);

        $this->userKey = $userKey;
        $this->credentialsKey = $credentialsKey;
        $this->credentialUserIdentifier = $credentialUserIdentifier;
    }

    protected function extractUsername(Request $request): string
    {
        $username = null;
        if ($request->server->has($this->userKey)) {
            $username = $request->server->get($this->userKey);
        } elseif (\is_string($dn = $request->server->get($this->credentialsKey))) {
            $username = $this->extractUsernameFromDn($dn);
        }

        if (null === $username) {
            throw new BadCredentialsException(\sprintf('SSL credentials not found: "%s", "%s".', $this->userKey, $this->credentialsKey));
        }

        return $username;
    }

    private function extractUsernameFromDn(string $dn): ?string
    {
        if ('emailAddress' === $this->credentialUserIdentifier) {
            $attribute = '(?:emailAddress|1\.2\.840\.113549\.1\.9\.1)';
            $value = '[^,/@]++@[^,/]++';
        } else {
            $attribute = preg_quote($this->credentialUserIdentifier, '#');
            $value = '[^,/]++';
        }

        $rdns = self::splitDn($dn);

        // In the legacy format, some servers don't escape slashes inside values, so a DN that contains the attribute twice can't be trusted
        if (str_starts_with($dn, '/') && 1 < preg_match_all('#'.$attribute.'=#', implode(',', $rdns))) {
            return null;
        }

        foreach ($rdns as $rdn) {
            if (preg_match('#^\s*'.$attribute.'=('.$value.')$#D', $rdn, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    /**
     * Splits a serialized DN into its RDNs, unescaping each of them.
     *
     * RFC 2253, used by Apache and nginx by default, separates RDNs with commas and
     * escapes special characters inside values with a backslash or as \HH. It also
     * allows quoting values instead, as OpenSSL does with XN_FLAG_ONELINE.
     *
     * X509_NAME_oneline(), used by Apache's LegacyDNStringFormat and by nginx's
     * $ssl_client_s_dn_legacy, prefixes the DN with a slash and separates RDNs with
     * slashes. It escapes non-printable bytes as \xHH and, since OpenSSL 3, slashes and
     * plus signs as \/ and \+. It never escapes a backslash, so any other backslash is
     * a literal one. Older OpenSSL versions leave slashes inside values bare, which
     * makes their output ambiguous: no parser can tell such a slash from a separator.
     *
     * Splitting happens on unescaped separators only, and each RDN is unescaped
     * afterwards, so that a separator inside a value can never pose as a boundary.
     *
     * @return string[]
     */
    private static function splitDn(string $dn): array
    {
        $legacy = str_starts_with($dn, '/');
        $separator = $legacy ? '/' : ',';
        $rdns = [];
        $rdn = '';
        $quoted = false;

        for ($i = $legacy ? 1 : 0, $length = \strlen($dn); $i < $length; ++$i) {
            $char = $dn[$i];

            if ($separator === $char && !$quoted) {
                $rdns[] = $rdn;
                $rdn = '';
            } elseif ('"' === $char && !$legacy) {
                $quoted = !$quoted;
            } elseif ('\\' !== $char || $i + 1 === $length) {
                $rdn .= $char;
            } elseif (!$legacy) {
                if (2 === strspn(substr($dn, $i + 1, 2), '0123456789abcdefABCDEF')) {
                    $rdn .= \chr(hexdec(substr($dn, $i + 1, 2)));
                    $i += 2;
                } else {
                    $rdn .= $dn[++$i];
                }
            } elseif ('x' === $dn[$i + 1] && 2 === strspn(substr($dn, $i + 2, 2), '0123456789abcdefABCDEF')) {
                $rdn .= \chr(hexdec(substr($dn, $i + 2, 2)));
                $i += 3;
            } elseif ('/' === $dn[$i + 1] || '+' === $dn[$i + 1]) {
                $rdn .= $dn[++$i];
            } else {
                $rdn .= $char;
            }
        }
        $rdns[] = $rdn;

        return $rdns;
    }
}
