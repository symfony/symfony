<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Html;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Event\FormSubmitEvent;
use Symfony\Bridge\TuiHtml\Html\HtmlBuilder;

final class SubmissionSemanticsTest extends TestCase
{
    public function testSharedRulesPreserveNativePhpValuesAndLenientMethodPolicy()
    {
        $view = (new HtmlBuilder())->build('<form method=" POST "><textarea name="text" type="checkbox">hello</textarea><input name="repeat" value="one"><input name="repeat" value="two"><input type="checkbox" name="tags[]" value="a" checked><input type="checkbox" name="tags[]" value="b" checked><button id="go" type="invalid" name="action" value="save">Go</button></form>');
        $events = [];
        $view->querySelector('form')->onSubmit(static function (FormSubmitEvent $event) use (&$events) { $events[] = $event; });
        $button = $view->getById('go');
        $button->activate();
        self::assertCount(1, $events);
        self::assertSame('post', $events[0]->getMethod());
        self::assertSame(['text' => 'hello', 'repeat' => 'two', 'tags' => ['a', 'b'], 'action' => 'save'], $events[0]->getValues());
        self::assertSame($button, $events[0]->getSubmitter());
    }

    public function testUnnamedImageSubmitterUsesUnprefixedCoordinateFields()
    {
        $view = (new HtmlBuilder())->build('<form><input type="hidden" name="token" value="abc"><input id="go" type="image" alt="Go"></form>');
        $events = [];
        $view->querySelector('form')->onSubmit(static function (FormSubmitEvent $event) use (&$events) { $events[] = $event; });
        $view->getById('go')->activate();
        self::assertCount(1, $events);
        self::assertSame(['token' => 'abc', 'x' => '0', 'y' => '0'], $events[0]->getValues());
    }
}
