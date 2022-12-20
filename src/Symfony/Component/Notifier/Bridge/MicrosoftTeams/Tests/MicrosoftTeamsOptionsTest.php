<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Action\OpenUriAction;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\MicrosoftTeamsOptions;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Section\Section;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Notification\Notification;

final class MicrosoftTeamsOptionsTest extends TestCase
{
    public function testFromNotification()
    {
        $notification = (new Notification($subject = 'Subject'))
            ->content($content = 'Content');

        self::assertSame([
            'title' => $subject,
            'text' => $content,
            '@type' => 'MessageCard',
            '@context' => 'https://schema.org/extensions',
        ], MicrosoftTeamsOptions::fromNotification($notification)->toArray());
    }

    public function testGetRecipientIdReturnsRecipientWhenSetViaConstructor()
    {
        $options = new MicrosoftTeamsOptions([
            'recipient_id' => $recipient = '/webhookb2/foo',
        ]);

        self::assertSame($recipient, $options->getRecipientId());
    }

    public function testGetRecipientIdReturnsRecipientWhenSetSetter()
    {
        $options = (new MicrosoftTeamsOptions())
            ->recipient($recipient = '/webhookb2/foo');

        self::assertSame($recipient, $options->getRecipientId());
    }

    public function testGetRecipientIdReturnsNullIfNotSetViaConstructorAndSetter()
    {
        $options = new MicrosoftTeamsOptions();

        self::assertNull($options->getRecipientId());
    }

    public function testRecipientMethodThrowsIfValueDoesNotMatchRegex()
    {
        $options = new MicrosoftTeamsOptions();

        $recipient = 'foo';

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage(sprintf('"%s" require recipient id format to be "/webhookb2/{uuid}@{uuid}/IncomingWebhook/{id}/{uuid}", "%s" given.', MicrosoftTeamsOptions::class, $recipient));

        $options->recipient($recipient);
    }

    public function testSummaryViaConstructor()
    {
        $options = new MicrosoftTeamsOptions([
            'summary' => $summary = 'My summary',
        ]);

        self::assertSame($summary, $options->toArray()['summary']);
    }

    public function testSummaryViaSetter()
    {
        $options = (new MicrosoftTeamsOptions())
            ->summary($summary = 'My summary');

        self::assertSame($summary, $options->toArray()['summary']);
    }

    public function testTitleViaConstructor()
    {
        $options = new MicrosoftTeamsOptions([
            'title' => $title = 'My title',
        ]);

        self::assertSame($title, $options->toArray()['title']);
    }

    public function testTitleViaSetter()
    {
        $options = (new MicrosoftTeamsOptions())
            ->title($title = 'My title');

        self::assertSame($title, $options->toArray()['title']);
    }

    public function testTextViaConstructor()
    {
        $options = new MicrosoftTeamsOptions([
            'text' => $text = 'My text',
        ]);

        self::assertSame($text, $options->toArray()['text']);
    }

    public function testTextViaSetter()
    {
        $options = (new MicrosoftTeamsOptions())
            ->text($text = 'My text');

        self::assertSame($text, $options->toArray()['text']);
    }

    /**
     * @dataProvider validThemeColors
     */
    public function testThemeColorViaConstructor(string $themeColor)
    {
        $options = new MicrosoftTeamsOptions([
            'themeColor' => $themeColor,
        ]);

        self::assertSame($themeColor, $options->toArray()['themeColor']);
    }

    /**
     * @dataProvider validThemeColors
     */
    public function testThemeColorViaSetter(string $themeColor)
    {
        $options = (new MicrosoftTeamsOptions())
            ->themeColor($themeColor);

        self::assertSame($themeColor, $options->toArray()['themeColor']);
    }

    public function validThemeColors(): \Generator
    {
        yield ['#333'];
        yield ['#333333'];
        yield ['#fff'];
        yield ['#ff0000'];
        yield ['#FFF'];
        yield ['#FF0000'];
    }

    /**
     * @dataProvider invalidThemeColors
     */
    public function testThemeColorViaConstructorThrowsInvalidArgumentException(string $themeColor)
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('MessageCard themeColor must have a valid hex color format.');

        new MicrosoftTeamsOptions([
            'themeColor' => $themeColor,
        ]);
    }

    /**
     * @dataProvider invalidThemeColors
     */
    public function testThemeColorViaSetterThrowsInvalidArgumentException(string $themeColor)
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('MessageCard themeColor must have a valid hex color format.');

        (new MicrosoftTeamsOptions())
            ->themeColor($themeColor);
    }

    public function invalidThemeColors(): \Generator
    {
        yield [''];
        yield [' '];
        yield ['red'];
        yield ['#1'];
        yield ['#22'];
        yield ['#4444'];
        yield ['#55555'];
    }

    public function testSectionViaConstructor()
    {
        $options = new MicrosoftTeamsOptions([
            'sections' => $sections = [(new Section())->toArray()],
        ]);

        self::assertSame($sections, $options->toArray()['sections']);
    }

    public function testSectionViaSetter()
    {
        $options = (new MicrosoftTeamsOptions())
            ->section($section = new Section());

        self::assertSame([$section->toArray()], $options->toArray()['sections']);
    }

    public function testActionViaConstructor()
    {
        $options = new MicrosoftTeamsOptions([
            'potentialAction' => $actions = [(new OpenUriAction())->toArray()],
        ]);

        self::assertSame($actions, $options->toArray()['potentialAction']);
    }

    public function testActionViaSetter()
    {
        $options = (new MicrosoftTeamsOptions())
            ->action($action = new OpenUriAction());

        self::assertSame([$action->toArray()], $options->toArray()['potentialAction']);
    }

    public function testActionViaConstructorThrowsIfMaxNumberOfActionsIsReached()
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('MessageCard maximum number of "potentialAction" has been reached (4).');

        new MicrosoftTeamsOptions([
            'potentialAction' => [
                new OpenUriAction(),
                new OpenUriAction(),
                new OpenUriAction(),
                new OpenUriAction(),
                new OpenUriAction(),
            ],
        ]);
    }

    public function testActionViaSetterThrowsIfMaxNumberOfActionsIsReached()
    {
        $options = (new MicrosoftTeamsOptions())
            ->action(new OpenUriAction())
            ->action(new OpenUriAction())
            ->action(new OpenUriAction())
            ->action(new OpenUriAction());

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('MessageCard maximum number of "potentialAction" has been reached (4).');

        $options->action(new OpenUriAction());
    }

    public function testExpectedActorViaConstructor()
    {
        $options = new MicrosoftTeamsOptions([
            'expectedActors' => $expectedActors = ['Oskar'],
        ]);

        self::assertSame($expectedActors, $options->toArray()['expectedActors']);
    }

    public function testExpectedActorViaSetter()
    {
        $options = (new MicrosoftTeamsOptions())
            ->expectedActor($expectedActor = 'Oskar');

        self::assertSame([$expectedActor], $options->toArray()['expectedActors']);
    }

    public function testExpectedActorsViaConstructor()
    {
        $options = new MicrosoftTeamsOptions([
            'expectedActors' => $expectedActors = ['Oskar', 'Fabien'],
        ]);

        self::assertSame($expectedActors, $options->toArray()['expectedActors']);
    }

    public function testExpectedActorsViaSetter()
    {
        $options = (new MicrosoftTeamsOptions())
            ->expectedActor($expectedActor1 = 'Oskar')
            ->expectedActor($expectedActor2 = 'Fabien')
        ;

        self::assertSame([$expectedActor1, $expectedActor2], $options->toArray()['expectedActors']);
    }
}
