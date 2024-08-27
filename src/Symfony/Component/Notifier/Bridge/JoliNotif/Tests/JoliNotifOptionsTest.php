<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\JoliNotif\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\JoliNotif\JoliNotifOptions;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;

/**
 * @author Ahmed Ghanem <ahmedghanem7361@gmail.com>
 */
class JoliNotifOptionsTest extends TestCase
{
    public function testToArray()
    {
        $joliOptions = new JoliNotifOptions();

        $joliOptions->setIconPath('/sample/icon/path');
        $joliOptions->setExtraOption('subtitle', 'This is a subtitle');
        $joliOptions->setExtraOption('sound', 'Frog');

        $this->assertSame([
            'icon_path' => '/sample/icon/path',
            'extra_options' => [
                'subtitle' => 'This is a subtitle',
                'sound' => 'Frog',
            ],
        ], $joliOptions->toArray());
    }

    public function testNonExistExtraOption()
    {
        $joliOptions = new JoliNotifOptions();

        $this->expectException(InvalidArgumentException::class);

        $joliOptions->getExtraOption('non-exist-option');
    }
}
