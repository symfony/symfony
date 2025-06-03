<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Catalogue;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\MessageCatalogue;

class MessageCatalogueTest extends TestCase
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
