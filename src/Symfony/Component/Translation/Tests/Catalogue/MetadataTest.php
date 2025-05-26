<?php

namespace Symfony\Component\Translation\Tests\Catalogue;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\MessageCatalogue;

class MetadataTest extends TestCase
{
    public function testIcuMetadataKept()
    {
        $mc = new MessageCatalogue('en', ['messages' => ['a' => 'new_a']]);
        $metadata = ['metadata' => 'value'];
        $mc->setMetadata('a', $metadata, 'messages+intl-icu');
        $this->assertEquals($metadata, $mc->getMetadata('a', 'messages'));
        $this->assertEquals($metadata, $mc->getMetadata('a', 'messages+intl-icu'));
    }
}
