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
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

class RadioGroupTest extends TestCase
{
    public function testRequiredRadioGroupAcceptsAnotherCheckedMember()
    {
        $session = new HtmlRenderer()->createSession('<form id="f"><input type="radio" name="choice" required><input type="radio" name="choice" value="selected" checked></form>', new RenderingContext(100, 100));
        $form = $session->getDocument()->getElementById('f');
        self::assertSame(['choice' => 'selected'], $session->submit($form)?->values);
    }

    public function testExternalFormOwnersKeepActivationGroupsSeparate()
    {
        $session = new HtmlRenderer()->createSession('<form id="one"></form><form id="two"></form><input id="a" type="radio" form="one" name="choice" checked><input id="b" type="radio" form="two" name="choice"><input id="c" type="radio" form="one" name="choice">', new RenderingContext(100, 100));
        $document = $session->getDocument();
        $session->activate($document->getElementById('b'));
        self::assertTrue($session->isChecked($document->getElementById('a')));
        $session->activate($document->getElementById('c'));
        self::assertFalse($session->isChecked($document->getElementById('a')));
        self::assertTrue($session->isChecked($document->getElementById('b')));
    }

    public function testRadioValidityStylesApplyToEveryMemberOfTheRequiredGroup()
    {
        $session = new HtmlRenderer()->createSession('<style>input:invalid { opacity: .5 }</style><form><input id="a" type="radio" name="choice" required><input id="b" type="radio" name="choice"></form>', new RenderingContext(100, 100));
        $document = $session->getDocument();
        self::assertSame('.5', $session->render()->getComputedStyle($document->getElementById('b'))['opacity']);
        $session->activate($document->getElementById('b'));
        self::assertArrayNotHasKey('opacity', $session->render()->getComputedStyle($document->getElementById('a')));
    }
}
