<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\Tests\Section;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Action\ActionCard;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Action\ActionInterface;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Action\HttpPostAction;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Action\InvokeAddInCommandAction;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Action\OpenUriAction;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Section\Field\Activity;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Section\Field\Fact;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Section\Field\Image;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Section\Section;

final class SectionTest extends TestCase
{
    public function testTitle()
    {
        $section = (new Section())
            ->title($value = 'Symfony is great!');

        $this->assertSame($value, $section->toArray()['title']);
    }

    public function testText()
    {
        $section = (new Section())
            ->text($value = 'Community power is awesome!');

        $this->assertSame($value, $section->toArray()['text']);
    }

    /**
     * @dataProvider allowedActions
     */
    public function testAction(array $expected, ActionInterface $action)
    {
        $section = (new Section())
            ->action($action);

        $this->assertCount(1, $section->toArray()['potentialAction']);
        $this->assertSame($expected, $section->toArray()['potentialAction']);
    }

    public static function allowedActions(): \Generator
    {
        yield [[['@type' => 'ActionCard']], new ActionCard()];
        yield [[['@type' => 'HttpPOST']], new HttpPostAction()];
        yield [[['@type' => 'InvokeAddInCommand']], new InvokeAddInCommandAction()];
        yield [[['@type' => 'OpenUri']], new OpenUriAction()];
    }

    public function testActivity()
    {
        $activity = (new Activity())
            ->image($imageUrl = 'https://symfony.com/logo.png')
            ->title($title = 'Activities')
            ->subtitle($subtitle = 'for Admins only')
            ->text($text = 'Hey Symfony!');

        $section = (new Section())
            ->activity($activity);

        $this->assertSame(
            [
                'activityImage' => $imageUrl,
                'activityTitle' => $title,
                'activitySubtitle' => $subtitle,
                'activityText' => $text,
            ],
            $section->toArray()
        );
    }

    public function testImage()
    {
        $image = (new Image())
            ->image($imageUrl = 'https://symfony.com/logo.png')
            ->title($title = 'Symfony logo');

        $section = (new Section())
            ->image($image);

        $this->assertCount(1, $section->toArray()['images']);
        $this->assertSame(
            [
                ['image' => $imageUrl, 'title' => $title],
            ],
            $section->toArray()['images']
        );
    }

    public function testFact()
    {
        $fact = (new Fact())
            ->name($name = 'Current version')
            ->value($value = '5.3');

        $section = (new Section())
            ->fact($fact);

        $this->assertCount(1, $section->toArray()['facts']);
        $this->assertSame(
            [
                ['name' => $name, 'value' => $value],
            ],
            $section->toArray()['facts']
        );
    }

    public function testMarkdownWithTrue()
    {
        $action = (new Section())
            ->markdown(true);

        $this->assertTrue($action->toArray()['markdown']);
    }

    public function testMarkdownWithFalse()
    {
        $action = (new Section())
            ->markdown(false);

        $this->assertFalse($action->toArray()['markdown']);
    }
}
