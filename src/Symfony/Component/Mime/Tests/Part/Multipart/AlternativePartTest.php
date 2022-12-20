<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Tests\Part\Multipart;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Part\Multipart\AlternativePart;

class AlternativePartTest extends TestCase
{
    public function testConstructor()
    {
        $a = new AlternativePart();
        self::assertEquals('multipart', $a->getMediaType());
        self::assertEquals('alternative', $a->getMediaSubtype());
    }
}
