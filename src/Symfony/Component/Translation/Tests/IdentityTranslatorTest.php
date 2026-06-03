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

use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Contracts\Translation\Test\TranslatorTest;
use Symfony\Contracts\Translation\TranslatorInterface;

class IdentityTranslatorTest extends TranslatorTest
{
    private string $defaultLocale;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultLocale = \Locale::getDefault();
        \Locale::setDefault('en');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        \Locale::setDefault($this->defaultLocale);
    }

    public function getTranslator(): TranslatorInterface
    {
        return new IdentityTranslator();
    }
}
