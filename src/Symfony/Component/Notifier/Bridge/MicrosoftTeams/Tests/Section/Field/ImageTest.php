<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\Tests\Section\Field;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Section\Field\Image;

final class ImageTest extends TestCase
{
    public function testImage()
    {
        $field = (new Image())
            ->image($value = 'https://symfony.com/logo.png');

        $this->assertSame($value, $field->toArray()['image']);
    }

    public function testTitle()
    {
        $field = (new Image())
            ->title($value = 'Symfony is great!');

        $this->assertSame($value, $field->toArray()['title']);
    }
}
