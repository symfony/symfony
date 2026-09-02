<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\AmpSql\Transport;

use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

final class AmpSqlReceivedStamp implements NonSendableStampInterface
{
    public function __construct(
        private int|string $id,
    ) {
    }

    public function getId(): int|string
    {
        return $this->id;
    }
}
