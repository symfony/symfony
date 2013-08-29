<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Translation;

use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Bundle\FrameworkBundle\Translation\PhpExtractor;
use Symfony\Component\Translation\MessageCatalogue;

class PhpExtractorTest extends TestCase
{
    public function testExtraction()
    {
        // Arrange
        $extractor = new PhpExtractor();
        $extractor->setPrefix('prefix');
        $catalogue = new MessageCatalogue('en');

        // Act
        $extractor->extract(__DIR__.'/../Fixtures/Resources/views/', $catalogue);

        // Assert
        $this->assertCount(2, $catalogue->all('messages'), '->extract() should find 1 translation');
        $this->assertTrue($catalogue->has('single-quoted key'), '->extract() should find the "single-quoted key" message');
        $this->assertTrue($catalogue->has('double-quoted key'), '->extract() should find the "double-quoted key" message');
        $this->assertEquals('prefixsingle-quoted key', $catalogue->get('single-quoted key'), '->extract() should apply "prefix" as prefix');
    }
}
