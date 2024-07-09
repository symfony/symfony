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
            $this->getResourceData(self::STANDARD_RESOURCES['elements']),
            $referenceElements
        );
    }

    public function testAttributes()
    {
        if (!\in_array('https', stream_get_wrappers(), true)) {
            $this->markTestSkipped('"https" stream wrapper is not enabled.');
        }

        $this->assertSame(
            $this->getResourceData(self::STANDARD_RESOURCES['attributes']),
            array_keys(W3CReference::ATTRIBUTES)
        );
    }

    private function getResourceData(string $resource): array
    {
        return json_decode(
            file_get_contents($resource, false, stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]])),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );
    }
}
