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
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Section\Field\Activity;

final class ActivityTest extends TestCase
{
    public function testImage()
    {
        $field = (new Activity())
            ->image($value = 'https://symfony.com/logo.png');

        $this->assertSame($value, $field->toArray()['activityImage']);
    }

    public function testTitle()
    {
        $field = (new Activity())
            ->title($value = 'Symfony is great!');

        $this->assertSame($value, $field->toArray()['activityTitle']);
    }

    public function testSubtitle()
    {
        $field = (new Activity())
            ->subtitle($value = 'I am a subtitle!');

        $this->assertSame($value, $field->toArray()['activitySubtitle']);
    }

    public function testText()
    {
        $field = (new Activity())
            ->text($value = 'Text goes here');

        $this->assertSame($value, $field->toArray()['activityText']);
    }
}
