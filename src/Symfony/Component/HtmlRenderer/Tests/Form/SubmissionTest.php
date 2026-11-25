<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Form;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Form\Submission;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

final class SubmissionTest extends TestCase
{
    public function testSettingsNormalizeOnlyKnownMethodKeywordsAndHonorSubmitterAttributes()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><form action="/save" method="POST"><button formaction="" formmethod="invalid">Go</button></form>');
        $form = $document->querySelector('form');
        $button = $document->querySelector('button');
        self::assertSame(['/save', 'post'], Submission::settings($form));
        self::assertSame(['', 'get'], Submission::settings($form, $button));
        $button->setAttribute('formmethod', 'dialog');
        self::assertSame(['', 'dialog'], Submission::settings($form, $button));
        $button->setAttribute('formmethod', ' post ');
        self::assertSame(['', 'get'], Submission::settings($form, $button));
        self::assertSame(['', 'post'], Submission::settings($form, $button, true), 'The retained terminal API explicitly keeps its whitespace-tolerant method policy.');
    }

    public function testSuccessfulControlsExcludeNonSubmitButtonsAndRespectInputElementTypes()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><form><button id="s" type="invalid" name="send">Go</button><button id="b" type="button" name="ignore">No</button><input id="r" type="reset" name="reset"><textarea id="t" name="text" type="checkbox">hello</textarea></form>');
        $submit = $document->getElementById('s');
        self::assertTrue(Submission::isSubmitButton($submit));
        self::assertSame([], Submission::values($submit, 'yes'));
        self::assertSame(['send' => 'yes'], Submission::values($submit, 'yes', $submit));
        self::assertSame([], Submission::values($document->getElementById('b'), 'no', $submit));
        self::assertSame([], Submission::values($document->getElementById('r'), 'reset', $submit));
        self::assertSame(['text' => 'hello'], Submission::values($document->getElementById('t'), 'hello', $submit));
        self::assertSame([], Submission::values($document->getElementById('t'), [], $submit));
        self::assertSame(['text' => '0'], Submission::values($document->getElementById('t'), '0', $submit));
    }

    public function testImageSubmitterCoordinatesUseTheNameOrUnprefixedCoordinateNames()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><form><input id="image" type="image" name="pick"></form>');
        $image = $document->getElementById('image');
        self::assertSame([], Submission::values($image, 'ignored'));
        self::assertSame(['pick.x' => '0', 'pick.y' => '0'], Submission::values($image, 'ignored', $image));
        $image->removeAttribute('name');
        self::assertSame(['x' => '0', 'y' => '0'], Submission::values($image, 'ignored', $image));
    }

    public function testSessionUsesSharedSubmissionRulesWithoutChangingGroupedSelectValues()
    {
        $html = '<form action="/save" method="invalid"><textarea name="text" type="checkbox">hello</textarea><select name="choice" multiple><option selected value="a">A</option></select><input name="repeat" value="one"><input name="repeat" value="two"><input id="image" type="image" name="pick"></form>';
        $session = (new HtmlRenderer())->createSession($html, new RenderingContext(300, 200));
        $document = $session->getDocument();
        $submission = $session->submit($document->querySelector('form'), $document->getElementById('image'));
        self::assertSame('/save', $submission->action);
        self::assertSame('get', $submission->method);
        self::assertSame(['text' => 'hello', 'choice' => ['a'], 'repeat' => ['one', 'two'], 'pick.x' => '0', 'pick.y' => '0'], $submission->values);
    }
}
