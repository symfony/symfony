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
use Symfony\Component\Workflow\Attribute\Transition;
use Symfony\Component\Workflow\Exception\LogicException;

class TransitionTest extends TestCase
{
    public function testDescriptionIsStoredInTheMetadata()
    {
        $attribute = new Transition('draft', 'published', description: 'Makes the article public', metadata: ['label' => 'Publish']);

        $this->assertSame(['label' => 'Publish', 'description' => 'Makes the article public'], $attribute->metadata);
        $this->assertSame(['label' => 'Publish'], (new Transition('draft', 'published', metadata: ['label' => 'Publish']))->metadata);
    }

    public function testDescriptionAndDescriptionMetadataCannotBeUsedTogether()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "description" argument of "#[Symfony\Component\Workflow\Attribute\Transition]" cannot be used together with a "description" key in its "metadata" argument.');

        new Transition('draft', 'published', description: 'Makes the article public', metadata: ['description' => 'Publishes the article']);
    }
}
