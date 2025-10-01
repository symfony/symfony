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
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\StaticMessage;
use Symfony\Component\Translation\Translator;

class StaticMessageTest extends TestCase
{
    public function testTrans()
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            'Symfony is great!' => 'Symfony est super !',
        ], 'fr', '');

        $translatable = new StaticMessage('Symfony is great!');

        $this->assertSame('Symfony is great!', $translatable->trans($translator, 'fr'));
    }
}
