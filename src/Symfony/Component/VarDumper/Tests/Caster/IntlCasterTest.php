<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Caster;

use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

/**
 * @requires extension intl
 */
class IntlCasterTest extends TestCase
{
    use VarDumperTestTrait;

    public function testArrayIterator()
    {
        $var = new \MessageFormatter('en', 'Hello {name}');

        $expected = <<<EOTXT
MessageFormatter {
  locale: "en"
  pattern: "Hello {name}"
}
EOTXT;
        $this->assertDumpEquals($expected, $var);
    }
}
