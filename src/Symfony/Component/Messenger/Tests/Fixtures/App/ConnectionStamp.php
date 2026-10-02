<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Fixtures\App;

use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

final class ConnectionStamp implements NonSendableStampInterface
{
    public function __construct(public readonly mixed $connection)
    {
    }
}
