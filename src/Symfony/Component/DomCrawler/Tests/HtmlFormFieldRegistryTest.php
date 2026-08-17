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

use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Field\HtmlChoiceFormField;
use Symfony\Component\DomCrawler\Field\HtmlInputFormField;
use Symfony\Component\DomCrawler\Field\InputFormField;
use Symfony\Component\DomCrawler\HtmlFormFieldRegistry;

class HtmlFormFieldRegistryTest extends TestCase
{
    public function testAcceptsAnyName()
    {
        $registry = new HtmlFormFieldRegistry();
        $registry->add($field = $this->input('[t:dbt%3adate;]data_daterange_enddate_value'));

        $this->assertSame($field, $registry->get('[t:dbt%3adate;]data_daterange_enddate_value'));

        $registry->remove('[t:dbt%3adate;]data_daterange_enddate_value');
        $this->assertFalse($registry->has('[t:dbt%3adate;]data_daterange_enddate_value'));
    }

    public function testGetRejectsAnUnknownField()
    {
        $registry = new HtmlFormFieldRegistry();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unreachable field "foo".');

        $registry->get('foo');
    }

    public function testSetRejectsAnUnknownField()
    {
        $registry = new HtmlFormFieldRegistry();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unreachable field "foo".');

        $registry->set('foo', null);
    }

    public function testHas()
    {
        $registry = new HtmlFormFieldRegistry();
        $registry->add($this->input('foo[bar]'));

        $this->assertTrue($registry->has('foo'));
        $this->assertTrue($registry->has('foo[bar]'));
        $this->assertFalse($registry->has('bar'));
        $this->assertFalse($registry->has('foo[foo]'));
    }

    public function testRemove()
    {
        $registry = new HtmlFormFieldRegistry();
        $registry->add($this->input('foo'));
        $registry->remove('foo');

        $this->assertFalse($registry->has('foo'));
    }

    public function testRemoveLeavesAnUnknownPathAlone()
    {
        $registry = new HtmlFormFieldRegistry();
        $registry->remove('foo[bar]');

        $this->assertFalse($registry->has('foo'));
        $this->assertSame([], $registry->all());
    }

    public function testKeepsTheSpacesOfASegment()
    {
        $registry = new HtmlFormFieldRegistry();
        $registry->add($this->input('foo[ bar ]'));

        $this->assertTrue($registry->has('foo[ bar ]'));
        $this->assertSame(['foo[ bar ]'], array_keys($registry->all()));
    }

    public function testSupportsMultivaluedFields()
    {
        $registry = new HtmlFormFieldRegistry();
        $registry->add($this->input('foo[]'));
        $registry->add($this->input('foo[]'));
        $registry->add($this->input('bar[5]'));
        $registry->add($this->input('bar[]'));
        $registry->add($this->input('bar[baz]'));

        $this->assertSame(['foo[0]', 'foo[1]', 'bar[5]', 'bar[6]', 'bar[baz]'], array_keys($registry->all()));
    }

    public function testSetValues()
    {
        $registry = new HtmlFormFieldRegistry();
        $registry->add($this->input('foo[2]'));
        $registry->add($this->input('foo[bar][baz]'));

        $registry->set('foo', [2 => 'two', 'bar' => ['baz' => 'fbb']]);

        $this->assertSame('two', $registry->get('foo[2]')->getValue());
        $this->assertSame('fbb', $registry->get('foo[bar][baz]')->getValue());
    }

    public function testSetRejectsAValueOnACompoundField()
    {
        $registry = new HtmlFormFieldRegistry();
        $registry->add($this->input('foo[bar][baz]'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot set value on a compound field "foo[bar]".');

        $registry->set('foo[bar]', 'fbb');
    }

    public function testSetRejectsAnArrayOnASingleField()
    {
        $registry = new HtmlFormFieldRegistry();
        $registry->add($this->input('bar'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unreachable field "0".');

        $registry->set('bar', ['baz']);
    }

    public function testSetPassesAnArrayToAChoiceField()
    {
        $node = \Dom\HTMLDocument::createFromString('<!doctype html><body><select name="foo[]" multiple><option value="a">A</option><option value="b">B</option></select>', 0)->querySelector('select');

        $registry = new HtmlFormFieldRegistry();
        $registry->add(new HtmlChoiceFormField($node));

        $registry->set('foo', ['a', 'b']);
        $this->assertSame(['a', 'b'], $registry->get('foo')->getValue());
    }

    public function testAddRejectsAClassicField()
    {
        $document = new \DOMDocument();
        $document->loadHTML('<!doctype html><body><input name="foo" value="v">', \LIBXML_NOERROR);

        $registry = new HtmlFormFieldRegistry();

        $this->expectException(\TypeError::class);

        $registry->add(new InputFormField($document->getElementsByTagName('input')->item(0)));
    }

    private function input(string $name): HtmlInputFormField
    {
        $html = \sprintf('<!doctype html><body><input name="%s" value="v">', $name);

        return new HtmlInputFormField(\Dom\HTMLDocument::createFromString($html, 0)->querySelector('input'));
    }
}
