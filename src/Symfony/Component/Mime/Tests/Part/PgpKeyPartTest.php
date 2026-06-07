<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Tests\Part;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Part\PgpKeyPart;

class PgpKeyPartTest extends TestCase
{
    public function testPGPKeyPartWithStandardKeyName()
    {
        $part = (new PgpKeyPart(''))->toString();
        $this->assertStringContainsString('Content-Type: application/pgp-key', $part, 'Content-Type not found');
        $this->assertStringContainsString('Content-Disposition: attachment', $part, 'Content-Disposition not found');
        $this->assertStringContainsString('filename=public-key.asc', $part, 'filename not found');
    }

    public function testPGPKeyPartWithCustomKeyName()
    {
        $part = (new PgpKeyPart('', 'custom.asc'))->toString();
        $this->assertStringContainsString('Content-Type: application/pgp-key', $part, 'Content-Type not found');
        $this->assertStringContainsString('Content-Disposition: attachment', $part, 'Content-Disposition not found');
        $this->assertStringContainsString('filename=custom.asc', $part, 'filename not found');
    }
}
