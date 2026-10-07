<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Tests\Fixtures\AttributeWorkflow;

use Symfony\Component\Workflow\Attribute\Place;

enum TaskStep: string
{
    #[Place(metadata: ['label' => 'New'])]
    case New = 'new';

    #[Place(metadata: ['label' => 'Processing', 'bg_color' => '#eee'])]
    case Processing = 'processing';

    case Done = 'done';
    case Failed = 'failed';
}
