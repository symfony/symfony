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

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\EmojiExtension;

class EmojiExtensionTest extends TestCase
{
    /**
     * @testWith ["😂", ":joy:"]
     *           ["😂", ":joy:", "slack"]
     *           ["😂", ":joy:", "github"]
     */
    public function testEmojify(string $expected, string $string, ?string $catalog = null): void
    {
        $extension = new EmojiExtension();
        if ($catalog){
            $this->assertSame($expected, $extension->emojify($string, $catalog));
        } else {
            $this->assertSame($expected, $extension->emojify($string));
        }
    }
}
