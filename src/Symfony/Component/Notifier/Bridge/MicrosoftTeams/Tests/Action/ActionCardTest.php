<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\Tests\Action;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Action\ActionCard;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Action\ActionCardCompatibleActionInterface;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Action\HttpPostAction;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Action\Input\DateInput;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Action\Input\InputInterface;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Action\Input\MultiChoiceInput;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Action\Input\TextInput;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Action\OpenUriAction;

final class ActionCardTest extends TestCase
{
    public function testName()
    {
        $action = (new ActionCard())
            ->name($value = 'My name');

        $this->assertSame($value, $action->toArray()['name']);
    }

    /**
     * @dataProvider availableInputs
     */
    public function testInput(array $expected, InputInterface $input)
    {
        $action = (new ActionCard())
            ->input($input);

        $this->assertCount(1, $action->toArray()['inputs']);
        $this->assertSame($expected, $action->toArray()['inputs']);
    }

    public static function availableInputs(): \Generator
    {
        yield [[['@type' => 'DateInput']], new DateInput()];
        yield [[['@type' => 'TextInput']], new TextInput()];
        yield [[['@type' => 'MultichoiceInput']], new MultiChoiceInput()];
    }

    /**
     * @dataProvider compatibleActions
     */
    public function testAction(array $expected, ActionCardCompatibleActionInterface $action)
    {
        $section = (new ActionCard())
            ->action($action);

        $this->assertCount(1, $section->toArray()['actions']);
        $this->assertSame($expected, $section->toArray()['actions']);
    }

    public static function compatibleActions(): \Generator
    {
        yield [[['@type' => 'HttpPOST']], new HttpPostAction()];
        yield [[['@type' => 'OpenUri']], new OpenUriAction()];
    }

    public function testToArray()
    {
        $this->assertSame(
            [
                '@type' => 'ActionCard',
            ],
            (new ActionCard())->toArray()
        );
    }
}
