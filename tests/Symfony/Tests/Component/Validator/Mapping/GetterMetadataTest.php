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

use Symfony\Component\Validator\Mapping\GetterMetadata;
use Symfony\Tests\Component\Validator\Fixtures\Entity;

class GetterMetadataTest extends \PHPUnit_Framework_TestCase
{
    const CLASSNAME = 'Symfony\Tests\Component\Validator\Fixtures\Entity';

    public function testInvalidPropertyName()
    {
        $this->setExpectedException('Symfony\Component\Validator\Exception\ValidatorException');

        new GetterMetadata(self::CLASSNAME, 'foobar');
    }

    public function testGetValueFromPublicGetter()
    {
        // private getters don't work yet because ReflectionMethod::setAccessible()
        // does not exists yet in a stable PHP release

        $entity = new Entity('foobar');
        $metadata = new GetterMetadata(self::CLASSNAME, 'internal');

        $this->assertEquals('foobar from getter', $metadata->getValue($entity));
    }
}

