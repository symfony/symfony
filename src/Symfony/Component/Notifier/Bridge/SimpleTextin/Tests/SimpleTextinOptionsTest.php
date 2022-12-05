<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\SimpleTextin\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\SimpleTextin\SimpleTextinOptions;

class SimpleTextinOptionsTest extends TestCase
{
    public function testSimpleTextinOptions()
    {
        $simpleTextinOptions = (new SimpleTextinOptions())->setFrom('test_from')->setRecipientId('test_recipient');

        self::assertSame(['from' => 'test_from'], $simpleTextinOptions->toArray());
    }
}
