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
use Symfony\Component\Workflow\Attribute\AsWorkflow;
use Symfony\Component\Workflow\Exception\LogicException;

class AsWorkflowTest extends TestCase
{
    public function testSupportsAndSupportStrategyCannotBeUsedTogether()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "supports" and "supportStrategy" arguments of "#[Symfony\Component\Workflow\Attribute\AsWorkflow]" cannot be used together.');

        new AsWorkflow(supports: \stdClass::class, supportStrategy: 'app.support_strategy');
    }

    public function testMarkingPropertyAndMarkingStoreCannotBeUsedTogether()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "markingProperty" and "markingStore" arguments of "#[Symfony\Component\Workflow\Attribute\AsWorkflow]" cannot be used together.');

        new AsWorkflow(markingProperty: 'status', markingStore: 'app.marking_store');
    }

    public function testDescriptionIsStoredInTheMetadata()
    {
        $attribute = new AsWorkflow(description: 'Manages the articles', metadata: ['title' => 'Articles']);

        $this->assertSame(['title' => 'Articles', 'description' => 'Manages the articles'], $attribute->metadata);
        $this->assertSame(['title' => 'Articles'], (new AsWorkflow(metadata: ['title' => 'Articles']))->metadata);
    }

    public function testDescriptionAndDescriptionMetadataCannotBeUsedTogether()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "description" argument of "#[Symfony\Component\Workflow\Attribute\AsWorkflow]" cannot be used together with a "description" key in its "metadata" argument.');

        new AsWorkflow(description: 'Manages the articles', metadata: ['description' => 'Manages the posts']);
    }
}
