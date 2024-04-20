<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Tests\HtmlToTextConverter;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\HtmlToTextConverter\LeagueHtmlToMarkdownConverter;

class LeagueHtmlToMarkdownConverterTest extends TestCase
{
    public function testConvert()
    {
        $converter = new LeagueHtmlToMarkdownConverter();
        $this->assertSame('**HTML**', $converter->convert('<head><meta charset="utf-8"></head><b>HTML</b><style>css</style>', 'UTF-8'));
    }
}
