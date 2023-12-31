<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\UrlParser;

use Symfony\Component\HttpFoundation\Exception\Parser\InvalidUrlException;
use Symfony\Component\HttpFoundation\Exception\Parser\MissingHostException;
use Symfony\Component\HttpFoundation\Exception\Parser\MissingSchemeException;

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
final class UrlParser
{
    private function __construct()
    {
    }

    public static function parse(#[\SensitiveParameter] string $url, bool $requiresHost = false, bool $decodeAuth = true): Url
    {
        if (false === $params = parse_url($url)) {
            throw new InvalidUrlException($url);
        }

        if (!isset($params['scheme'])) {
            throw new MissingSchemeException();
        }

        if ($requiresHost && !isset($params['host'])) {
            throw new MissingHostException();
        }

        $params += [
            'port' => null,
            'user' => null,
            'pass' => null,
            'path' => null,
            'query' => null,
            'fragment' => null,
        ];

        $auth = [
            'user' => $params['user'],
            'pass' => $params['pass'],
        ];

        unset($params['user'], $params['pass']);

        if ($decodeAuth) {
            $auth = array_map(static fn (?string $param): ?string => \is_string($param) ? rawurldecode($param) : $param, $auth);
        }

        return new Url(
            ...$params,
            user: '' === $auth['user'] ? null : $auth['user'],
            password: '' === $auth['pass'] ? null : $auth['pass'],
        );
    }
}
