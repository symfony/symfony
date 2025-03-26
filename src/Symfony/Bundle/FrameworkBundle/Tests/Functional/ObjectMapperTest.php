<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\ObjectMapper\ObjectMapped;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\ObjectMapper\ObjectToBeMapped;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ObjectMapperTest extends AbstractWebTestCase
{
    public function testObjectMapper()
    {
        static::bootKernel(['test_case' => 'ObjectMapper']);

        /** @var Symfony\Component\ObjectMapper\ObjectMapperInterface<ObjectMapped> */
        $objectMapper = static::getContainer()->get('object_mapper.alias');
        $mapped = $objectMapper->map(new ObjectToBeMapped());
        $this->assertSame($mapped->a, 'transformed');
    }
}
