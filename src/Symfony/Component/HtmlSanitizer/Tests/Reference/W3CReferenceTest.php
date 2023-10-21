<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlSanitizer\Tests\Reference;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlSanitizer\Reference\W3CReference;

/**
 * Check that the W3CReference class is up to date with the standard resources.
 *
 * @see https://github.com/WICG/sanitizer-api/blob/main/resources
 */
class W3CReferenceTest extends TestCase
{
    private const STANDARD_RESOURCES = [
        'elements' => 'https://raw.githubusercontent.com/WICG/sanitizer-api/main/resources/baseline-element-allow-list.json',
        'attributes' => 'https://raw.githubusercontent.com/WICG/sanitizer-api/main/resources/baseline-attribute-allow-list.json',
    ];

    public function testElements()
    {
        if (!\in_array('https', stream_get_wrappers(), true)) {
            $this->markTestSkipped('"https" stream wrapper is not enabled.');
        }

        $referenceElements = array_values(array_merge(array_keys(W3CReference::HEAD_ELEMENTS), array_keys(W3CReference::BODY_ELEMENTS)));
        sort($referenceElements);

        $this->assertSame(
            json_decode(file_get_contents(self::STANDARD_RESOURCES['elements']), true, 512, \JSON_THROW_ON_ERROR),
            $referenceElements
        );
    }

    public function testAttributes()
    {
        if (!\in_array('https', stream_get_wrappers(), true)) {
            $this->markTestSkipped('"https" stream wrapper is not enabled.');
        }

        $this->assertSame(
            json_decode(file_get_contents(self::STANDARD_RESOURCES['attributes']), true, 512, \JSON_THROW_ON_ERROR),
            array_keys(W3CReference::ATTRIBUTES)
        );
    }
}
