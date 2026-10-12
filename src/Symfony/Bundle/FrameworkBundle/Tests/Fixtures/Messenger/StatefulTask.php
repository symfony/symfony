<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger;

use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCronTask(expression: '0 * * * *', schedule: 'stateful')]
class StatefulTask
{
    public function __invoke()
    {
    }
}
