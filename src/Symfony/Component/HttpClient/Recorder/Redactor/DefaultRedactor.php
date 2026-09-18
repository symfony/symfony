<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Recorder\Redactor;

/**
 * Masks credentials and tokens defined by:
 *   - RFC 6749 (OAuth 2.0): access_token, refresh_token, client_secret, password, code
 *   - RFC 7521 / RFC 7523 (assertion grants): assertion, client_assertion
 *   - RFC 7591 (dynamic client registration): registration_access_token
 *   - RFC 7636 (PKCE): code_verifier
 *   - RFC 7662 / RFC 7009 (introspection, revocation): token
 *   - RFC 8628 (device flow): device_code, user_code, verification_uri_complete
 *   - RFC 8693 (token exchange): subject_token, actor_token
 *   - RFC 9449 (DPoP): DPoP header
 *   - OpenID Connect Core 1.0: id_token
 *   - OpenID Connect Back-Channel Logout 1.0: logout_token
 *   - OpenID Connect CIBA Core 1.0: auth_req_id
 *   - SAML 2.0 Bindings: SAMLRequest, SAMLResponse, SAMLart
 * plus common non-standard names (api_key, x-api-key, x-auth-token, secret, key).
 * See https://www.iana.org/assignments/oauth-parameters/oauth-parameters.xhtml
 */
final class DefaultRedactor implements RedactorInterface
{
    private const MASK = '[REDACTED]';

    private const DEFAULT_HEADERS = [
        'authorization',
        'proxy-authorization',
        'cookie',
        'set-cookie',
        'x-api-key',
        'x-auth-token',
        'dpop',
    ];

    private const DEFAULT_QUERY_PARAMS = [
        'token',
        'access_token',
        'api_key',
        'apikey',
        'secret',
        'password',
        'key',
        'client_secret',
        'code',
        'id_token',
        'refresh_token',
        'user_code',
        'samlrequest',
        'samlresponse',
        'samlart',
    ];

    private const DEFAULT_BODY_FIELDS = [
        'password',
        'secret',
        'token',
        'access_token',
        'api_key',
        'client_secret',
        'authorization',
        'refresh_token',
        'id_token',
        'client_assertion',
        'assertion',
        'code_verifier',
        'device_code',
        'user_code',
        'verification_uri_complete',
        'subject_token',
        'actor_token',
        'registration_access_token',
        'auth_req_id',
        'logout_token',
        'samlresponse',
        'samlrequest',
        'samlart',
    ];

    private readonly array $headerDenyList;
    private readonly array $queryParamDenyList;
    private readonly array $bodyFieldDenyList;

    /**
     * @param string[] $headers     header names to mask, added to the built-in list (case-insensitive)
     * @param string[] $queryParams query-string and form field names to mask, added to the built-in list (case-insensitive)
     * @param string[] $bodyFields  JSON and form field names to mask, added to the built-in list (case-insensitive)
     */
    public function __construct(array $headers = [], array $queryParams = [], array $bodyFields = [])
    {
        $this->headerDenyList = array_merge(self::DEFAULT_HEADERS, array_map(strtolower(...), $headers));
        $this->queryParamDenyList = array_merge(self::DEFAULT_QUERY_PARAMS, array_map(strtolower(...), $queryParams));
        $this->bodyFieldDenyList = array_merge(self::DEFAULT_BODY_FIELDS, array_map(strtolower(...), $bodyFields));
    }

    public function redactUrl(string $url): string
    {
        $parts = parse_url($url);

        if (!isset($parts['query']) && !isset($parts['fragment']) && !isset($parts['user'])) {
            return $url;
        }

        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);

            $masked = false;
            $query = $this->maskKeys($query, $this->queryParamDenyList, $masked);

            $queryString = http_build_query($query);
        } else {
            $queryString = '';
        }

        $fragment = $parts['fragment'] ?? '';
        if ('' !== $fragment && str_contains($fragment, '=')) {
            parse_str($fragment, $fragmentParts);

            $masked = false;
            $fragmentParts = $this->maskKeys($fragmentParts, $this->queryParamDenyList, $masked);

            $fragment = http_build_query($fragmentParts);
        }

        $rebuilt = '';
        if (isset($parts['scheme'])) {
            $rebuilt .= $parts['scheme'].'://';
        }
        if (isset($parts['user'])) {
            $rebuilt .= rawurlencode(self::MASK).'@';
        }
        $rebuilt .= $parts['host'] ?? '';
        $rebuilt .= isset($parts['port']) ? ':'.$parts['port'] : '';
        $rebuilt .= $parts['path'] ?? '';
        $rebuilt .= '' !== $queryString ? '?'.$queryString : '';
        $rebuilt .= '' !== $fragment ? '#'.$fragment : '';

        return $rebuilt;
    }

    public function redactHeaders(array $headers): array
    {
        $redacted = [];

        foreach ($headers as $name => $values) {
            $values = (array) $values;
            $lowerName = strtolower((string) $name);

            if (\in_array($lowerName, $this->headerDenyList, true)) {
                $redacted[$name] = array_fill(0, \count($values), self::MASK);
            } elseif ('location' === $lowerName) {
                $redacted[$name] = array_map($this->redactUrl(...), $values);
            } else {
                $redacted[$name] = $values;
            }
        }

        return $redacted;
    }

    public function redactBody(?string $body): ?string
    {
        if (null === $body || '' === $body) {
            return $body;
        }

        $decoded = json_decode($body, true);

        if (!\is_array($decoded) || \JSON_ERROR_NONE !== json_last_error()) {
            return $this->redactFormEncodedBody($body);
        }

        $masked = false;
        $decoded = $this->maskKeys($decoded, $this->bodyFieldDenyList, $masked);

        if (!$masked) {
            return $body;
        }

        return json_encode($decoded, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
    }

    private function redactFormEncodedBody(string $body): string
    {
        if (!preg_match('/^(?:[^=&\s]+=[^&\s]*)(?:&[^=&\s]+=[^&\s]*)*$/', $body)) {
            return $body;
        }

        parse_str($body, $params);

        $masked = false;
        $params = $this->maskKeys($params, array_merge($this->bodyFieldDenyList, $this->queryParamDenyList), $masked);

        if (!$masked) {
            return $body;
        }

        return http_build_query($params);
    }

    /**
     * Masks every value whose key is deny-listed, including whole sub-trees, and recurses into the others.
     */
    private function maskKeys(array $data, array $denyList, bool &$masked): array
    {
        foreach ($data as $key => $value) {
            if (\is_string($key) && \in_array(strtolower($key), $denyList, true)) {
                $data[$key] = self::MASK;
                $masked = true;
            } elseif (\is_array($value)) {
                $data[$key] = $this->maskKeys($value, $denyList, $masked);
            }
        }

        return $data;
    }
}
