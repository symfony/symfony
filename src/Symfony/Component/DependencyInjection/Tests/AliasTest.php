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
        $alias->setDeprecated('vendor/package', '1.1', 'The %alias_id% service is deprecated.');

        $this->assertTrue($alias->isDeprecated());
    }

    /**
     * @group legacy
     * @expectedDeprecation Since symfony/dependency-injection 5.1: The signature of method "Symfony\Component\DependencyInjection\Alias::setDeprecated()" requires 3 arguments: "string $package, string $version, string $message", not defining them is deprecated.
     */
    public function testItHasADefaultDeprecationMessage()
    {
        $alias = new Alias('foo', false);
        $alias->setDeprecated();

        $expectedMessage = 'The "foo" service alias is deprecated. You should stop using it, as it will be removed in the future.';
        $this->assertEquals($expectedMessage, $alias->getDeprecation('foo')['message']);
    }

    /**
     * @group legacy
     * @expectedDeprecation Since symfony/dependency-injection 5.1: The signature of method "Symfony\Component\DependencyInjection\Alias::setDeprecated()" requires 3 arguments: "string $package, string $version, string $message", not defining them is deprecated.
     */
    public function testSetDeprecatedWithoutPackageAndVersion()
    {
        $def = new Alias('stdClass');
        $def->setDeprecated(true, '%alias_id%');

        $deprecation = $def->getDeprecation('deprecated_alias');
        $this->assertSame('deprecated_alias', $deprecation['message']);
        $this->assertSame('', $deprecation['package']);
        $this->assertSame('', $deprecation['version']);
    }

    public function testReturnsCorrectDeprecation()
    {
        $alias = new Alias('foo', false);
        $alias->setDeprecated('vendor/package', '1.1', 'The "%alias_id%" is deprecated.');

        $deprecation = $alias->getDeprecation('foo');
        $this->assertEquals('The "foo" is deprecated.', $deprecation['message']);
        $this->assertEquals('vendor/package', $deprecation['package']);
        $this->assertEquals('1.1', $deprecation['version']);
    }

    /**
     * @group legacy
     * @expectedDeprecation Since symfony/dependency-injection 5.1: The signature of method "Symfony\Component\DependencyInjection\Alias::setDeprecated()" requires 3 arguments: "string $package, string $version, string $message", not defining them is deprecated.
     * @expectedDeprecation Since symfony/dependency-injection 5.1: Passing a null message to un-deprecate a node is deprecated.
     */
    public function testCanOverrideDeprecation()
    {
        $alias = new Alias('foo', false);
        $alias->setDeprecated('vendor/package', '1.1', 'The "%alias_id%" is deprecated.');
        $this->assertTrue($alias->isDeprecated());

        $alias->setDeprecated(false);
        $this->assertFalse($alias->isDeprecated());
    }

    /**
     * @dataProvider invalidDeprecationMessageProvider
     */
    public function testCannotDeprecateWithAnInvalidTemplate($message)
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $def = new Alias('foo');
        $def->setDeprecated('package', '1.1', $message);
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
