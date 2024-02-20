<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare (strict_types=1);

namespace Symfony\Component\AccessToken\Tests\Unit\Credentials;

use PHPUnit\Framework\TestCase;
use Symfony\Component\AccessToken\Credentials\BasicAuthCredentials;

/**
 * @author Pierre Rineau <pierre.rineau@processus.org>
 */
class BasicAuthCredentialsTest extends TestCase
{
    public function testBasics(): void
    {
        $credentials = new BasicAuthCredentials('foo', 'bar');

        self::assertSame('foo', $credentials->getUsername());
        self::assertSame('bar', $credentials->getPassword());
    }

    public function testIdentifierIsComputed(): void
    {
        $credentials = new BasicAuthCredentials('foo', 'bar');

        self::assertSame('foo', $credentials->getId());
    }
}
