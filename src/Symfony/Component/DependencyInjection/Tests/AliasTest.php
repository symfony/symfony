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
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

class AliasTest extends TestCase
{
    public function testConstructor()
    {
        $alias = new Alias('foo');

        $this->assertEquals('foo', (string) $alias);
        $this->assertFalse($alias->isPublic());
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
        $this->assertTrue($alias->isPrivate());
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
     * @dataProvider invalidDeprecationMessageProvider
     */
    public function testCannotDeprecateWithAnInvalidTemplate($message)
    {
        $this->expectException(InvalidArgumentException::class);
        $def = new Alias('foo');
        $def->setDeprecated('package', '1.1', $message);
    }

    public static function invalidDeprecationMessageProvider()
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
