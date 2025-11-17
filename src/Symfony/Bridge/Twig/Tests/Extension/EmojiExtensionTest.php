<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\EmojiExtension;

#[RequiresPhpExtension('intl')]
class EmojiExtensionTest extends TestCase
{
    #[TestWith(['🅰️', ':a:'])]
    #[TestWith(['🅰️', ':a:', 'slack'])]
    #[TestWith(['🅰', ':a:', 'github'])]
    public function testEmojify(string $expected, string $string, ?string $catalog = null)
    {
        $extension = new EmojiExtension();
        $this->assertSame($expected, $extension->emojify($string, $catalog));
    }
}
