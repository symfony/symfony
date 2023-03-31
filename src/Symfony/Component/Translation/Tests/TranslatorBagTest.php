<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\TranslatorBag;

class TranslatorBagTest extends TestCase
{
    public function testAll()
    {
        $catalogue = new MessageCatalogue('en', $messages = ['domain1' => ['foo' => 'foo'], 'domain2' => ['bar' => 'bar']]);

        $bag = new TranslatorBag();
        $bag->addCatalogue($catalogue);

        $this->assertEquals(['en' => $messages], $this->getAllMessagesFromTranslatorBag($bag));

        $messages = ['domain1+intl-icu' => ['foo' => 'bar']] + $messages + [
                'domain2+intl-icu' => ['bar' => 'foo'],
                'domain3+intl-icu' => ['biz' => 'biz'],
            ];
        $catalogue = new MessageCatalogue('en', $messages);

        $bag = new TranslatorBag();
        $bag->addCatalogue($catalogue);

        $this->assertEquals([
            'en' => [
                'domain1' => ['foo' => 'bar'],
                'domain2' => ['bar' => 'foo'],
                'domain3' => ['biz' => 'biz'],
            ],
        ], $this->getAllMessagesFromTranslatorBag($bag));
    }

    public function testDiff()
    {
        $catalogueA = new MessageCatalogue('en', ['domain1' => ['foo' => 'foo', 'bar' => 'bar'], 'domain2' => ['baz' => 'baz', 'qux' => 'qux']]);

        $bagA = new TranslatorBag();
        $bagA->addCatalogue($catalogueA);

        $catalogueB = new MessageCatalogue('en', ['domain1' => ['foo' => 'foo'], 'domain2' => ['baz' => 'baz', 'corge' => 'corge']]);

        $bagB = new TranslatorBag();
        $bagB->addCatalogue($catalogueB);

        $bagResult = $bagA->diff($bagB);

        $this->assertEquals([
            'en' => [
                'domain1' => ['bar' => 'bar'],
                'domain2' => ['qux' => 'qux'],
            ],
        ], $this->getAllMessagesFromTranslatorBag($bagResult));
    }

    public function testDiffWithIntlDomain()
    {
        $catalogueA = new MessageCatalogue('en', [
            'domain1+intl-icu' => ['foo' => 'foo', 'bar' => 'bar'],
            'domain2' => ['baz' => 'baz', 'qux' => 'qux'],
        ]);

        $bagA = new TranslatorBag();
        $bagA->addCatalogue($catalogueA);

        $catalogueB = new MessageCatalogue('en', [
            'domain1' => ['foo' => 'foo'],
            'domain2' => ['baz' => 'baz', 'corge' => 'corge'],
        ]);

        $bagB = new TranslatorBag();
        $bagB->addCatalogue($catalogueB);

        $bagResult = $bagA->diff($bagB);

        $this->assertEquals([
            'en' => [
                'domain1' => ['bar' => 'bar'],
                'domain2' => ['qux' => 'qux'],
            ],
        ], $this->getAllMessagesFromTranslatorBag($bagResult));
    }

    public function testIntersect()
    {
        $catalogueA = new MessageCatalogue('en', ['domain1' => ['foo' => 'foo', 'bar' => 'bar'], 'domain2' => ['baz' => 'baz', 'qux' => 'qux']]);

        $bagA = new TranslatorBag();
        $bagA->addCatalogue($catalogueA);

        $catalogueB = new MessageCatalogue('en', ['domain1' => ['foo' => 'foo', 'baz' => 'baz'], 'domain2' => ['baz' => 'baz', 'corge' => 'corge']]);

        $bagB = new TranslatorBag();
        $bagB->addCatalogue($catalogueB);

        $bagResult = $bagA->intersect($bagB);

        $this->assertEquals([
            'en' => [
                'domain1' => ['foo' => 'foo'],
                'domain2' => ['baz' => 'baz'],
            ],
        ], $this->getAllMessagesFromTranslatorBag($bagResult));
    }

    private function getAllMessagesFromTranslatorBag(TranslatorBag $translatorBag): array
    {
        $allMessages = [];
        foreach ($translatorBag->getCatalogues() as $catalogue) {
            $allMessages[$catalogue->getLocale()] = $catalogue->all();
        }

        return $allMessages;
    }
}
