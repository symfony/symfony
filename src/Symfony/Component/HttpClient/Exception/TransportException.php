<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Exception;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class TransportException extends \RuntimeException implements TransportExceptionInterface
{
    public static function connectionTimeoutReached(string $url, float $seconds): self
    {
        return new self(sprintf('Failed connecting to "%s". The idle timeout was reached after %s second%s.', $url, $seconds, $seconds > 1 ? 's' : ''));
    }

    public static function readTimeoutReached(string $url, float $seconds): self
    {
        return new self(sprintf('Failed reading the complete response stream from "%s". The idle timeout was reached after %s second%s.', $url, $seconds, $seconds > 1 ? 's' : ''));
    }
}
