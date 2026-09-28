<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

use Symfony\Component\JsonStreamer\Attribute\StreamedName;
use Symfony\Component\JsonStreamer\Attribute\ValueTransformer;

class DummyWithQuotes
{
    #[StreamedName("it's \\' quoted")]
    public int $name = 1;

    #[ValueTransformer(nativeToStream: "double'it", streamToNative: "divide'it")]
    public int $transformed = 10;
}
