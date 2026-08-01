<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\FacebookPage\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\FacebookPage\FacebookPageOptions;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
final class FacebookPageOptionsTest extends TestCase
{
    public function testGetRecipientIdIsAlwaysNull()
    {
        self::assertNull((new FacebookPageOptions())->getRecipientId());
        self::assertNull((new FacebookPageOptions())->link('https://example.com')->getRecipientId());
    }

    public function testLink()
    {
        $options = (new FacebookPageOptions())->link('https://example.com/article');

        self::assertSame('https://example.com/article', $options->getLink());
        self::assertSame(['link' => 'https://example.com/article'], $options->toArray());
    }

    public function testToArrayOmitsEmptyLink()
    {
        self::assertSame([], (new FacebookPageOptions())->toArray());
    }
}
