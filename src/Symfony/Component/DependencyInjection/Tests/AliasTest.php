<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Alias;

class AliasTest extends TestCase
{
    public function testConstructor()
    {
        $alias = new Alias('foo');

        $this->assertEquals('foo', (string) $alias);
        $this->assertTrue($alias->isPublic());
    }

    public function testCanConstructANonPublicAlias()
    {
        $alias = new Alias('foo', false);

        $this->assertEquals('foo', (string) $alias);
        $this->assertFalse($alias->isPublic());
    }

    public function testCanConstructAPrivateAlias()
    {
        $alias = new Alias('foo', false, false);

        $this->assertEquals('foo', (string) $alias);
        $this->assertFalse($alias->isPublic());
        $this->assertFalse($alias->isPrivate());
    }

    public function testCanSetPublic()
    {
        $alias = new Alias('foo', false);
        $alias->setPublic(true);

        $this->assertTrue($alias->isPublic());
    }

    public function testCanDeprecateAnAlias()
    {
        $alias = new Alias('foo', false);
        $alias->setDeprecated(true, 'The %alias_id% service is deprecated.');

        $this->assertTrue($alias->isDeprecated());
    }

    public function testItHasADefaultDeprecationMessage()
    {
        $alias = new Alias('foo', false);
        $alias->setDeprecated();

        $expectedMessage = 'The "foo" service alias is deprecated. You should stop using it, as it will be removed in the future.';
        $this->assertEquals($expectedMessage, $alias->getDeprecationMessage('foo'));
    }

    public function testReturnsCorrectDeprecationMessage()
    {
        $alias = new Alias('foo', false);
        $alias->setDeprecated(true, 'The "%alias_id%" is deprecated.');

        $expectedMessage = 'The "foo" is deprecated.';
        $this->assertEquals($expectedMessage, $alias->getDeprecationMessage('foo'));
    }

    public function testCanOverrideDeprecation()
    {
        $alias = new Alias('foo', false);
        $alias->setDeprecated();
        $this->assertTrue($alias->isDeprecated());

        $alias->setDeprecated(false);
        $this->assertFalse($alias->isDeprecated());
    }

    /**
     * @dataProvider invalidDeprecationMessageProvider
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     */
    public function testCannotDeprecateWithAnInvalidTemplate($message)
    {
        $def = new Alias('foo');
        $def->setDeprecated(true, $message);
    }

    public function invalidDeprecationMessageProvider()
    {
        return [
            "With \rs" => ["invalid \r message %alias_id%"],
            "With \ns" => ["invalid \n message %alias_id%"],
            'With */s' => ['invalid */ message %alias_id%'],
            'message not containing required %alias_id% variable' => ['this is deprecated'],
            'template not containing required %alias_id% variable' => [true],
        ];
    }
}
