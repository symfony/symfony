<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Attribute\Place;
use Symfony\Component\Workflow\Exception\LogicException;

class PlaceTest extends TestCase
{
    public function testDescriptionIsStoredInTheMetadata()
    {
        $attribute = new Place('Waiting for a review', ['label' => 'Draft']);

        $this->assertSame(['label' => 'Draft', 'description' => 'Waiting for a review'], $attribute->metadata);
        $this->assertSame(['label' => 'Draft'], (new Place(metadata: ['label' => 'Draft']))->metadata);
    }

    public function testDescriptionAndDescriptionMetadataCannotBeUsedTogether()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "description" argument of "#[Symfony\Component\Workflow\Attribute\Place]" cannot be used together with a "description" key in its "metadata" argument.');

        new Place('Waiting for a review', ['description' => 'Waiting for a reviewer']);
    }
}
