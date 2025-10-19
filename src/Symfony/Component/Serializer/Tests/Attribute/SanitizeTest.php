<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Attribute\Sanitize;

/**
 * @author Mohamed Senoussi <lesfootix@gmail.com>
 */
class SanitizeTest extends TestCase
{
    public function testDefaultSanitizer(): void
    {
        $attr = new Sanitize();
        $this->assertNull($attr->sanitizer);
    }

    public function testCustomSanitizer(): void
    {
        $attr = new Sanitize(sanitizer: 'permissive');
        $this->assertSame('permissive', $attr->sanitizer);
    }
}
