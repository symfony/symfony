<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Locale\Stub;

require_once __DIR__.'/../TestCase.php';

use Symfony\Component\Locale\Locale;
use Symfony\Component\Locale\Stub\StubIntl;
use Symfony\Component\Locale\Tests\TestCase as LocaleTestCase;

class StubIntlTest extends LocaleTestCase
{
    public function codeProvider()
    {
        return array (
            array(-129, '[BOGUS UErrorCode]'),
            array(0, 'U_ZERO_ERROR'),
            array(1, 'U_ILLEGAL_ARGUMENT_ERROR'),
            array(9, 'U_PARSE_ERROR'),
            array(129, '[BOGUS UErrorCode]'),
        );
    }

    /**
     * @dataProvider codeProvider
     */
    public function testGetErrorName($code, $name)
    {
        $this->assertSame($name, StubIntl::getErrorName($code));
    }

    /**
     * @dataProvider codeProvider
     */
    public function testGetErrorNameWithIntl($code, $name)
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $this->assertSame(intl_error_name($code), StubIntl::getErrorName($code));
    }
}
