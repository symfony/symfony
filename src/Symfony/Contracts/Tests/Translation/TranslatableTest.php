<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Contracts\Tests\Translation;

use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\Translatable;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait;

class TranslatableTest extends TestCase
{
    public function testTranslatable()
    {
        $translatable = new Translatable('Symfony is %what%!', ['%what%' => 'awesome'], 'domain');

        $translator = new class() implements TranslatorInterface {
            use TranslatorTrait;
        };

        $this->assertSame('Symfony is %what%!', $translatable->getMessage());
        $this->assertSame(['%what%' => 'awesome'], $translatable->getParameters());
        $this->assertSame('domain', $translatable->getDomain());
        $this->assertSame('Symfony is awesome!', $translatable->trans($translator));

        $this->assertNull((new Translatable('Hello'))->getDomain());
    }

    public function testToString()
    {
        $this->assertSame('Symfony is great!', (string) new Translatable('Symfony is great!'));
    }
}
