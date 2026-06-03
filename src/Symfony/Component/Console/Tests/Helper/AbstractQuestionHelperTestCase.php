<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Helper;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\StreamableInputInterface;

abstract class AbstractQuestionHelperTestCase extends TestCase
{
    protected function createStreamableInputInterfaceMock($stream = null, $interactive = true)
    {
        $mock = $this->createStub(StreamableInputInterface::class);
        $mock
            ->method('isInteractive')
            ->willReturn($interactive);

        if ($stream) {
            $mock
                ->method('getStream')
                ->willReturn($stream);
        }

        return $mock;
    }
}
