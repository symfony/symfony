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
     * @param string[] $names  header, query-string, form and JSON field names to mask, added to the built-in lists (case-insensitive)
     * @param string[] $except names never masked, removed from the built-in lists (case-insensitive)
     */
    public function __construct(array $names = [], array $except = [])
    {
        $names = array_map(strtolower(...), $names);
        $except = array_map(strtolower(...), $except);

        $this->headerDenyList = array_values(array_diff(array_merge(self::DEFAULT_HEADERS, $names), $except));
        $this->queryParamDenyList = array_values(array_diff(array_merge(self::DEFAULT_QUERY_PARAMS, $names), $except));
        $this->bodyFieldDenyList = array_values(array_diff(array_merge(self::DEFAULT_BODY_FIELDS, $names), $except));
    }

    public function redactUrl(string $url): string
    {
        [$url, $fragment] = explode('#', $url, 2) + [1 => null];
        [$url, $query] = explode('?', $url, 2) + [1 => null];

        if (preg_match('#^([a-zA-Z][a-zA-Z0-9+.-]*+://)[^/@]*+@#', $url, $m)) {
            $url = $m[1].rawurlencode(self::MASK).'@'.substr($url, \strlen($m[0]));
        }

        if (null !== $query) {
            $url .= '?'.$this->maskPairs($query, $this->queryParamDenyList);
        }

        if (null !== $fragment) {
            $url .= '#'.(str_contains($fragment, '=') ? $this->maskPairs($fragment, $this->queryParamDenyList) : $fragment);
        }

        return $url;
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

        if (!\in_array($body[strspn($body, " \t\n\r")] ?? '', ['{', '['], true) || !json_validate($body)) {
            return $this->redactFormEncodedBody($body);
        }

        return $this->maskJson($body);
    }

    /**
     * Masks deny-listed members in place, so that everything else stays byte-identical:
     * decoding and re-encoding would turn {} into [], 1.0 into 1 and lose the precision of big integers.
     *
     * The body must be valid JSON; it is scanned once, without regular expressions, so that large bodies are handled too.
     */
    private function maskJson(string $json): string
    {
        $redacted = '';
        $copyFrom = 0;
        $containers = [];
        $expectKey = false;
        $length = \strlen($json);

        for ($i = 0; $i < $length;) {
            switch ($json[$i]) {
                case '"':
                    $end = self::skipString($json, $i);

                    if (!$expectKey) {
                        $i = $end;
                        break;
                    }

                    $expectKey = false;
                    $key = json_decode(substr($json, $i, $end - $i));
                    $i = $end + strspn($json, " \t\n\r", $end) + 1; // past the colon
                    $i += strspn($json, " \t\n\r", $i);

                    if (\in_array(strtolower($key), $this->bodyFieldDenyList, true)) {
                        $redacted .= substr($json, $copyFrom, $i - $copyFrom).json_encode(self::MASK);
                        $i = $copyFrom = self::skipValue($json, $i);
                    }
                    break;

                case '{':
                    $containers[] = '{';
                    $expectKey = true;
                    ++$i;
                    break;

                case '[':
                    $containers[] = '[';
                    ++$i;
                    break;

                case '}':
                case ']':
                    array_pop($containers);
                    ++$i;
                    break;

                case ',':
                    $expectKey = '{' === end($containers);
                    ++$i;
                    break;

                default:
                    $i += strcspn($json, '"{}[],', $i);
            }
        }

        return $redacted.substr($json, $copyFrom);
    }

    /**
     * Returns the offset right after the string starting at $i.
     */
    private static function skipString(string $json, int $i): int
    {
        while (true) {
            $i += 1 + strcspn($json, '"\\', $i + 1);

            if ('"' === $json[$i]) {
                return $i + 1;
            }

            ++$i; // the escaped character is skipped by the next strcspn()
        }
    }

    /**
     * Returns the offset right after the value starting at $i.
     */
    private static function skipValue(string $json, int $i): int
    {
        if ('"' === $json[$i]) {
            return self::skipString($json, $i);
        }

        if ('{' !== $json[$i] && '[' !== $json[$i]) {
            return $i + strcspn($json, " \t\n\r,}]", $i);
        }

        for ($depth = 0;;) {
            $i += strcspn($json, '"{}[]', $i);

            if ('"' === $json[$i]) {
                $i = self::skipString($json, $i);
            } elseif ('{' === $json[$i] || '[' === $json[$i]) {
                ++$depth;
                ++$i;
            } elseif (0 === --$depth) {
                return $i + 1;
            } else {
                ++$i;
            }
        }
    }

    private function redactFormEncodedBody(string $body): string
    {
        if (!preg_match('/^(?:[^=&\s]+=[^&\s]*)(?:&[^=&\s]+=[^&\s]*)*$/', $body)) {
            return $body;
        }

        return $this->maskPairs($body, array_merge($this->bodyFieldDenyList, $this->queryParamDenyList));
    }

    /**
     * Masks the values of deny-listed "name=value" pairs in place, so that everything else stays byte-identical:
     * parsing and rebuilding would merge repeated names, rename "a.b" to "a_b" and turn "flag" into "flag=".
     *
     * A nested name like "user[password]" is masked when any of its segments is deny-listed.
     */
    private function maskPairs(string $pairs, array $denyList): string
    {
        $pairs = explode('&', $pairs);

        foreach ($pairs as $i => $pair) {
            if (false === $eq = strpos($pair, '=')) {
                continue;
            }

            foreach (preg_split('/[\[\]]++/', urldecode(substr($pair, 0, $eq)), -1, \PREG_SPLIT_NO_EMPTY) as $name) {
                if (\in_array(strtolower($name), $denyList, true)) {
                    $pairs[$i] = substr($pair, 0, $eq + 1).urlencode(self::MASK);
                    break;
                }
            }
        }

        return implode('&', $pairs);
    }
}
