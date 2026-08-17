<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Form;

/**
 * Pins which fields a form collects and in which order.
 *
 * Field order decides how same-named fields overwrite each other in the
 * registry, so it is part of the behavior and not an implementation detail.
 */
class FormFieldOrderTest extends TestCase
{
    private function form(string $html, string $buttonSelector = 'form'): Form
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('<!doctype html><html><body>'.$html.'</body></html>', \LIBXML_NOERROR);

        $node = 'form' === $buttonSelector
            ? $dom->getElementsByTagName('form')->item(0)
            : $dom->getElementById($buttonSelector);

        return new Form($node, 'http://localhost/');
    }

    #[DataProvider('provideForms')]
    public function testCollectedFieldsAndOrder(string $html, array $expected)
    {
        $this->assertSame($expected, array_keys($this->form($html)->all()));
    }

    public static function provideForms(): iterable
    {
        yield 'document order is preserved' => [
            '<form><input name="a"><select name="b"></select><textarea name="c"></textarea><input name="d"></form>',
            ['a', 'b', 'c', 'd'],
        ];

        yield 'nested markup does not reorder' => [
            '<form><div><input name="a"></div><input name="b"><fieldset><div><input name="c"></div></fieldset></form>',
            ['a', 'b', 'c'],
        ];

        yield 'a descendant pointing at another form is excluded' => [
            '<form id="f"><input name="in"><input name="out" form="other"></form>',
            ['in'],
        ];

        yield 'an external field pointing at this form is collected' => [
            '<form id="f"><input name="in"></form><input name="ext" form="f">',
            ['in', 'ext'],
        ];

        yield 'fields inside a template are excluded' => [
            '<form><input name="a"><template><input name="tpl"></template><input name="b"></form>',
            ['a', 'b'],
        ];

        yield 'a turbo-stream inside a template is collected again' => [
            '<form><input name="a"><template><turbo-stream><input name="ts"></turbo-stream></template></form>',
            ['a', 'ts'],
        ];

        yield 'a later same-named field wins' => [
            '<form><input name="a" value="first"><input name="a" value="second"></form>',
            ['a'],
        ];

        yield 'unnamed fields are skipped' => [
            '<form><input><input name="a"></form>',
            ['a'],
        ];
    }

    public function testLaterSameNamedFieldOverwritesTheEarlierOne()
    {
        $form = $this->form('<form><input name="a" value="first"><input name="a" value="second"></form>');

        $this->assertSame('second', $form->get('a')->getValue());
    }

    public function testSubmitButtonIsCollectedBeforeTheFormFields()
    {
        $html = '<form id="f"><input name="a"><button id="btn" name="go" value="1">go</button></form>';

        $this->assertSame(['go', 'a'], array_keys($this->form($html, 'btn')->all()));
    }

    public function testImageButtonContributesCoordinatePairs()
    {
        $html = '<form id="f"><input name="a"><input id="btn" type="image" name="pic"></form>';

        $this->assertSame(['pic.x', 'pic.y', 'a'], array_keys($this->form($html, 'btn')->all()));
    }

    public function testFormAttributeOnTheButtonSelectsThatForm()
    {
        $html = '<form id="one"><input name="in_one"></form><form id="two"><input name="in_two"></form>'
            .'<button id="btn" form="two" name="go" value="1">go</button>';

        $this->assertSame(['go', 'in_two'], array_keys($this->form($html, 'btn')->all()));
    }

    public function testValuesReflectCollectionOrder()
    {
        $form = $this->form('<form><input name="a" value="1"><input name="b" value="2"></form>');

        $this->assertSame(['a' => '1', 'b' => '2'], $form->getValues());
    }
}
