<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Builder\CodeGenerator;

use App\CodeGenerator\_Method;
use PHPUnit\Framework\TestCase;

class MethodTest extends TestCase
{
    public function testEmpty()
    {
        $output = _Method::create('foobar')->toString();
        $this->assertEquals('public function foobar()
{
}', $output);
    }

    public function testAbstractMethod()
    {
        $output = _Method::create('foobar')->setVisibility('protected')->setBody(null)->toString();
        $this->assertEquals('abstract protected function foobar();', $output);
    }

    public function testVisibility()
    {
        $output = _Method::create('foobar')->setVisibility('private')->toString();
        $this->assertEquals('private function foobar()
{
}', $output);

        $output = _Method::create('foobar')->setVisibility('protected')->toString();
        $this->assertEquals('protected function foobar()
{
}', $output);

        // We dont care about logic
        $output = _Method::create('foobar')->setVisibility('crazy')->toString();
        $this->assertEquals('crazy function foobar()
{
}', $output);
    }

    public function testReturnType()
    {
        $output = _Method::create('foobar')->setReturnType('int')->toString();
        $this->assertEquals('public function foobar(): int
{
}', $output);

        $output = _Method::create('foobar')->setReturnType('mixed')->toString();
        $this->assertEquals('public function foobar(): mixed
{
}', $output);

        $output = _Method::create('foobar')->setReturnType('?string')->toString();
        $this->assertEquals('public function foobar(): ?string
{
}', $output);
    }

    public function testArguments()
    {
        $output = _Method::create('foobar')
            ->addArgument('foo')
            ->addArgument('bar', null, 'test')
            ->addArgument('baz', null, null)
            ->toString();
        $this->assertEquals('public function foobar($foo, $bar = \'test\', $baz = NULL)
{
}', $output);

        $output = _Method::create('foobar')
            ->addArgument('foo', 'int')
            ->addArgument('bar', 'string')
            ->addArgument('baz', '?string', null)
            ->toString();
        $this->assertEquals('public function foobar(int $foo, string $bar, ?string $baz = NULL)
{
}', $output);
    }

    public function testBody()
    {
        $output = _Method::create('foobar')
            ->setBody('return 2;')
            ->toString('    ');
        $this->assertEquals('public function foobar()
{
    return 2;
}', $output);
    }
}
