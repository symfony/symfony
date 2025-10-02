<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Workflow;

use Symfony\Component\Workflow\Attribute\Place;

enum TicketStatus: string
{
    case Open = 'open';

    #[Place(metadata: ['label' => 'Resolved'])]
    case Resolved = 'resolved';

    case Closed = 'closed';
}
