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
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\DomCrawler\Field\HtmlChoiceFormField;
use Symfony\Component\DomCrawler\Field\HtmlFormField;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\DomCrawler\HtmlCrawler;
use Symfony\Component\DomCrawler\HtmlForm;

class HtmlFormTest extends TestCase
{
    /**
     * The native and the classic form must read the same document the same way.
     */
    #[DataProvider('provideForms')]
    public function testMatchesClassic(string $html, string $selector)
    {
        $native = $this->nativeForm($html, $selector);
        $classic = $this->classicForm($html, $selector);

        $this->assertSame($classic->getMethod(), $native->getMethod());
        $this->assertSame($classic->getName(), $native->getName());
        $this->assertSame($classic->getUri(), $native->getUri());
        $this->assertSame($classic->getValues(), $native->getValues());
        $this->assertSame($classic->getPhpValues(), $native->getPhpValues());
        $this->assertSame($classic->getFiles(), $native->getFiles());
        $this->assertSame($classic->getPhpFiles(), $native->getPhpFiles());
        $this->assertSame(array_keys($classic->all()), array_keys($native->all()));
        $this->assertSame($this->fieldKinds($classic), $this->fieldKinds($native));
    }

    public static function provideForms(): iterable
    {
        yield 'text input' => ['<form action="/x"><input type="text" name="t" value="v"></form>', 'form'];
        yield 'method is uppercased' => ['<form action="/x" method="post"><input name="t" value="v"></form>', 'form'];
        yield 'named form' => ['<form action="/x" name="myform"><input name="t" value="v"></form>', 'form'];
        yield 'get method merges the values into the query' => ['<form action="/x?a=b"><input name="t" value="v"></form>', 'form'];
        yield 'every field kind' => ['<form action="/x" method="post"><input name="t" value="v"><textarea name="ta">hello</textarea><select name="s"><option value="a">A</option><option value="b" selected>B</option></select><input type="checkbox" name="c" value="1" checked><input type="radio" name="r" value="1"><input type="radio" name="r" value="2" checked><input type="file" name="f"></form>', 'form'];
        yield 'unchecked checkbox is left out' => ['<form action="/x" method="post"><input type="checkbox" name="c" value="1"></form>', 'form'];
        yield 'disabled fields are left out' => ['<form action="/x" method="post"><input name="a" value="1" disabled><input name="b" value="2"></form>', 'form'];
        yield 'fields without a name are left out' => ['<form action="/x" method="post"><input value="1"><input name="" value="2"><input name="c" value="3"></form>', 'form'];
        yield 'submit buttons are left out' => ['<form action="/x" method="post"><input type="submit" name="s" value="go"><input type="button" name="b" value="go"><input name="t" value="v"></form>', 'form'];
        yield 'array notation' => ['<form action="/x" method="post"><input name="a[b][c]" value="1"><input name="a[b][d]" value="2"><input name="l[]" value="3"><input name="l[]" value="4"></form>', 'form'];
        yield 'multiple select' => ['<form action="/x" method="post"><select name="s[]" multiple><option value="a" selected>A</option><option value="b" selected>B</option></select></form>', 'form'];
        yield 'nested form tags are collected once' => ['<form action="/x" method="post"><div><input name="t" value="v"></div></form>', 'form'];
        yield 'fields in a template are inert' => ['<form action="/x" method="post"><template><input name="hidden" value="x"></template><input name="shown" value="y"></form>', 'form'];
        yield 'a turbo-stream outside a template is live' => ['<form action="/x" method="post"><turbo-stream><input name="streamed" value="x"></turbo-stream><input name="shown" value="y"></form>', 'form'];
        yield 'form attribute pulls an outside field in' => ['<form action="/x" method="post" id="f1"><input name="in" value="1"></form><input name="out" value="2" form="f1">', 'form'];
        yield 'form attribute pushes a descendant out' => ['<form action="/x" method="post" id="f1"><input name="in" value="1"><input name="other" value="2" form="f2"></form>', 'form'];
        yield 'built from a submit button' => ['<form action="/x" method="post"><input name="t" value="v"><input type="submit" name="s" value="go"></form>', 'input[type=submit]'];
        yield 'built from a button tag' => ['<form action="/x" method="post"><input name="t" value="v"><button name="b" value="go">go</button></form>', 'button'];
        yield 'formaction overrides the action' => ['<form action="/x" method="post"><input type="submit" name="s" value="go" formaction="/y"></form>', 'input[type=submit]'];
        yield 'formmethod overrides the method' => ['<form action="/x" method="post"><input type="submit" name="s" value="go" formmethod="put"></form>', 'input[type=submit]'];
        yield 'image input becomes x and y' => ['<form action="/x" method="post"><input type="image" name="i" alt="go"></form>', 'input[type=image]'];
        yield 'button with a form attribute' => ['<form action="/x" method="post" id="f1"><input name="t" value="v"></form><button name="b" value="go" form="f1">go</button>', 'button'];
        yield 'no action' => ['<form method="post"><input name="t" value="v"></form>', 'form'];
    }

    /**
     * The HTML parser keeps the content of a template out of the tree, and PHP
     * exposes no way to reach it, so a field inside a template stays out of the
     * form even when a turbo-stream wraps it. loadHTML() knows nothing about
     * templates, so the classic form can bring such a field back.
     */
    public function testATurboStreamInsideATemplateDoesNotBringFieldsBack()
    {
        $html = '<form action="/x" method="post"><template><turbo-stream><input name="back" value="x"></turbo-stream></template><input name="shown" value="y"></form>';

        $this->assertSame(['shown' => 'y'], $this->nativeForm($html, 'form')->getValues());
        $this->assertSame(['back' => 'x', 'shown' => 'y'], $this->classicForm($html, 'form')->getValues());
    }

    public function testGetFormNodeReturnsTheNativeElement()
    {
        $form = $this->nativeForm('<form action="/x"><input name="t" value="v"></form>', 'form');

        $this->assertInstanceOf(\Dom\Element::class, $form->getFormNode());
        $this->assertSame('form', $form->getFormNode()->localName);
    }

    public function testSetValues()
    {
        $form = $this->nativeForm('<form action="/x" method="post"><input name="a" value="1"><input name="b" value="2"></form>', 'form');
        $form->setValues(['a' => 'x', 'b' => 'y']);

        $this->assertSame(['a' => 'x', 'b' => 'y'], $form->getValues());
    }

    public function testArrayAccess()
    {
        $form = $this->nativeForm('<form action="/x" method="post"><input name="a" value="1"></form>', 'form');

        $this->assertTrue(isset($form['a']));
        $this->assertFalse(isset($form['b']));
        $this->assertInstanceOf(HtmlFormField::class, $form['a']);

        $form['a'] = 'x';
        $this->assertSame('x', $form['a']->getValue());

        unset($form['a']);
        $this->assertFalse($form->has('a'));
    }

    public function testGetAndSetAField()
    {
        $form = $this->nativeForm('<form action="/x" method="post"><input name="a" value="1"></form>', 'form');
        $field = $form->get('a');

        $this->assertInstanceOf(HtmlFormField::class, $field);

        $form->remove('a');
        $this->assertFalse($form->has('a'));

        $form->set($field);
        $this->assertTrue($form->has('a'));
    }

    public function testARadioGroupCollectsEveryOption()
    {
        $form = $this->nativeForm('<form action="/x" method="post"><input type="radio" name="r" value="1"><input type="radio" name="r" value="2" checked></form>', 'form');

        $this->assertSame(['1', '2'], $form->get('r')->availableOptionValues());

        $form['r'] = '1';
        $this->assertSame(['r' => '1'], $form->getValues());
    }

    public function testGetRejectsAnUnknownField()
    {
        $form = $this->nativeForm('<form action="/x"><input name="a" value="1"></form>', 'form');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unreachable field "b".');

        $form->get('b');
    }

    public function testDisableValidation()
    {
        $form = $this->nativeForm('<form action="/x" method="post"><select name="s"><option value="a">A</option></select></form>', 'form');
        $form->disableValidation();
        $form['s'] = 'unknown';

        $this->assertSame(['s' => 'unknown'], $form->getValues());
    }

    public function testFilesAreOnlySentByAWritingMethod()
    {
        $html = '<form action="/x" method="%s"><input type="file" name="f"></form>';

        $this->assertSame([], $this->nativeForm(\sprintf($html, 'get'), 'form')->getFiles());
        $this->assertArrayHasKey('f', $this->nativeForm(\sprintf($html, 'post'), 'form')->getFiles());
    }

    public function testBaseHrefIsUsedWhenTheActionIsNotEmpty()
    {
        $crawler = new HtmlCrawler('<!doctype html><body><form action="foo"><input name="t" value="v"></form>', 'http://localhost/bar/', 'http://example.com/base/');

        $this->assertSame('http://example.com/base/foo?t=v', $crawler->filter('form')->form()->getUri());
    }

    public function testBaseHrefIsIgnoredWhenTheActionIsEmpty()
    {
        $crawler = new HtmlCrawler('<!doctype html><body><form><input name="t" value="v"></form>', 'http://localhost/bar/', 'http://example.com/base/');

        $this->assertSame('http://localhost/bar/?t=v', $crawler->filter('form')->form()->getUri());
    }

    public function testSetNodeRejectsANonFormTag()
    {
        $node = \Dom\HTMLDocument::createFromString('<!doctype html><body><div></div>', 0)->querySelector('div');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Unable to submit on a "div" tag.');

        new HtmlForm($node, 'http://localhost/');
    }

    public function testSetNodeRejectsAButtonWithoutAFormAncestor()
    {
        $node = \Dom\HTMLDocument::createFromString('<!doctype html><body><button></button>', 0)->querySelector('button');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The selected node does not have a form ancestor.');

        new HtmlForm($node, 'http://localhost/');
    }

    public function testSetNodeRejectsAnInvalidFormAttribute()
    {
        $node = \Dom\HTMLDocument::createFromString('<!doctype html><body><button form="nope"></button>', 0)->querySelector('button');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The selected node has an invalid form attribute (nope).');

        new HtmlForm($node, 'http://localhost/');
    }

    public function testCrawlerFormPassesTheValues()
    {
        $crawler = new HtmlCrawler('<!doctype html><body><form action="/x" method="post"><input name="a" value="1"></form>', 'http://localhost/');
        $form = $crawler->filter('form')->form(['a' => 'x'], 'PUT');

        $this->assertInstanceOf(HtmlForm::class, $form);
        $this->assertSame(['a' => 'x'], $form->getValues());
        $this->assertSame('PUT', $form->getMethod());
    }

    public function testCrawlerFormOnAnEmptyNodeListThrows()
    {
        $crawler = new HtmlCrawler('<!doctype html><body><p>x</p>', 'http://localhost/');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The current node list is empty.');

        $crawler->filter('form')->form();
    }

    /**
     * @return array<string, string>
     */
    private function fieldKinds(Form|HtmlForm $form): array
    {
        $kinds = [];
        foreach ($form->all() as $name => $field) {
            $kind = str_replace('Html', '', (new \ReflectionClass($field))->getShortName());

            if ($field instanceof HtmlChoiceFormField || $field instanceof ChoiceFormField) {
                $kind .= ':'.$field->getType();
            }

            $kinds[$name] = $kind;
        }

        return $kinds;
    }

    private function nativeForm(string $html, string $selector): HtmlForm
    {
        return (new HtmlCrawler('<!doctype html><body>'.$html, 'http://localhost/base/'))->filter($selector)->form();
    }

    private function classicForm(string $html, string $selector): Form
    {
        return (new Crawler('<!doctype html><body>'.$html, 'http://localhost/base/'))->filter($selector)->form();
    }
}
