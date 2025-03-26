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
 */
class W3CReferenceTest extends TestCase
{
    public function testElements()
    {
        $referenceElements = array_values(array_merge(array_keys(W3CReference::HEAD_ELEMENTS), array_keys(W3CReference::BODY_ELEMENTS)));
        sort($referenceElements);

        $this->assertSame(
            $this->getResourceData(__DIR__.'/../Fixtures/baseline-element-allow-list.json'),
            $referenceElements
        );
    }

    public function testAttributes()
    {
        $this->assertSame(
            $this->getResourceData(__DIR__.'/../Fixtures/baseline-attribute-allow-list.json'),
            array_keys(W3CReference::ATTRIBUTES)
        );
    }

    private function getResourceData(string $resource): array
    {
        return json_decode(
            file_get_contents($resource),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );
    }
}
