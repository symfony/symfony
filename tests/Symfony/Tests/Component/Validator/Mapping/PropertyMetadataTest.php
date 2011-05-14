<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Validator\Mapping;

require_once __DIR__.'/../Fixtures/Entity.php';

use Symfony\Component\Validator\Mapping\PropertyMetadata;
use Symfony\Tests\Component\Validator\Fixtures\Entity;
use Symfony\Tests\Component\Validator\Fixtures\MagicGetter;

class PropertyMetadataTest extends \PHPUnit_Framework_TestCase
{
    const CLASSNAME = 'Symfony\Tests\Component\Validator\Fixtures\Entity';
    const MAGIC_CLASSNAME = 'Symfony\Tests\Component\Validator\Fixtures\MagicGetter';

    public function testInvalidPropertyName()
    {
        $this->setExpectedException('Symfony\Component\Validator\Exception\ValidatorException');

        new PropertyMetadata(self::CLASSNAME, 'foobar');
    }

    public function testGetValueFromPrivateProperty()
    {
        $entity = new Entity('foobar');
        $metadata = new PropertyMetadata(self::CLASSNAME, 'internal');

        $this->assertEquals('foobar', $metadata->getValue($entity));
    }

    public function testGetValueFromMagicGet()
    {
        $entity = new MagicGetter;
        $metadata = new PropertyMetadata(self::MAGIC_CLASSNAME, 'amagicproperty');

        $this->assertEquals('Magic Get Value', $metadata->getValue($entity));
    }

    public function testInvalidValueFromMagicGet()
    {
        $entity = new MagicGetter;
        $metadata = new PropertyMetadata(self::MAGIC_CLASSNAME, 'amagicproperty');

        $this->assertNotEquals('Not a Magic Get Value', $metadata->getValue($entity));
    }
}

