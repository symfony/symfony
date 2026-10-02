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

use Symfony\Component\Messenger\Stamp\StampInterface;

final class SeenByWorkerStamp implements StampInterface
{
    /**
     * @param list<class-string<StampInterface>> $stampClasses
     */
    public function __construct(
        public readonly array $stampClasses,
        public readonly ?bool $trusted,
    ) {
    }
}
