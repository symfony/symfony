<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Attribute;

use Symfony\Component\Routing\Exception\InvalidArgumentException;

/**
 * The RFC 9745 deprecation of a route, meant to be used in {@see Route}.
 *
 * @see https://www.rfc-editor.org/rfc/rfc9745.html
 * @see https://www.rfc-editor.org/rfc/rfc8594.html
 *
 * @author Mohamed Senoussi <lesfootix@gmail.com>
 */
final class RouteDeprecation
{
    public readonly \DateTimeImmutable $since;
    public readonly ?\DateTimeImmutable $sunset;

    /**
     * @param \DateTimeInterface|string      $since  The date the route is or will be deprecated; strings without a timezone are read as UTC
     * @param \DateTimeInterface|string|null $sunset The date the route is expected to become unresponsive, which cannot be earlier than $since
     * @param string|null                    $link   The URI of the documentation describing the deprecation
     */
    public function __construct(
        \DateTimeInterface|string $since,
        \DateTimeInterface|string|null $sunset = null,
        public readonly ?string $link = null,
    ) {
        $this->since = self::toDate('since', $since);
        $this->sunset = null !== $sunset ? self::toDate('sunset', $sunset) : null;

        if (null !== $this->sunset && $this->sunset < $this->since) {
            throw new InvalidArgumentException('The "sunset" date of a route deprecation cannot be earlier than its "since" date.');
        }

        if (null !== $link && ('' === $link || preg_match('/[\x00-\x20\x7F<>]/', $link))) {
            throw new InvalidArgumentException(\sprintf('The "link" of a route deprecation must be a URI without whitespace, control characters, "<" or ">", "%s" given.', $link));
        }
    }

    public static function fromArray(array $config): self
    {
        if ($extraKeys = array_diff_key($config, ['since' => true, 'sunset' => true, 'link' => true])) {
            throw new InvalidArgumentException(\sprintf('A route deprecation only accepts the "since", "sunset" and "link" keys, "%s" given.', implode('", "', array_keys($extraKeys))));
        }

        if (!isset($config['since'])) {
            throw new InvalidArgumentException('A route deprecation requires a "since" date.');
        }

        foreach (['since', 'sunset'] as $key) {
            if (isset($config[$key]) && !\is_string($config[$key]) && !$config[$key] instanceof \DateTimeInterface) {
                throw new InvalidArgumentException(\sprintf('The "%s" date of a route deprecation must be a string or a "DateTimeInterface", "%s" given.%s', $key, get_debug_type($config[$key]), \is_int($config[$key]) ? ' Did you forget to quote the date in YAML?' : ''));
            }
        }

        if (isset($config['link']) && !\is_string($config['link'])) {
            throw new InvalidArgumentException(\sprintf('The "link" of a route deprecation must be a string, "%s" given.', get_debug_type($config['link'])));
        }

        return new self($config['since'], $config['sunset'] ?? null, $config['link'] ?? null);
    }

    /**
     * @return array{since: string, sunset?: string, link?: string}
     */
    public function toArray(): array
    {
        $config = ['since' => $this->since->format(\DATE_ATOM)];

        if (null !== $this->sunset) {
            $config['sunset'] = $this->sunset->format(\DATE_ATOM);
        }

        if (null !== $this->link) {
            $config['link'] = $this->link;
        }

        return $config;
    }

    private static function toDate(string $key, \DateTimeInterface|string $date): \DateTimeImmutable
    {
        if (\is_string($date)) {
            try {
                $date = new \DateTimeImmutable($date, new \DateTimeZone('UTC'));
            } catch (\Exception $e) {
                throw new InvalidArgumentException(\sprintf('The "%s" date of a route deprecation must be a valid date, "%s" given.', $key, $date), 0, $e);
            }
        }

        return \DateTimeImmutable::createFromInterface($date)->setTimezone(new \DateTimeZone('UTC'));
    }
}
