<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Middleware\Configuration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Middleware\Configuration\ValidationConfiguration;
use Symfony\Component\Validator\Constraints\GroupSequence;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class ValidationConfigurationTest extends TestCase
{
    public function testConfig()
    {
        $config = new ValidationConfiguration($groups = array('Default', 'Extra'));
        $this->assertSame($groups, $config->getGroups());

        $config = new ValidationConfiguration($groups = new GroupSequence(array('Default', 'Then')));
        $this->assertSame($groups, $config->getGroups());
    }

    public function testSerialiazable()
    {
        $this->assertTrue(is_subclass_of(ValidationConfiguration::class, \Serializable::class, true));
        $this->assertEquals($config = new ValidationConfiguration(array('Default', 'Extra')), unserialize(serialize($config)));
        $this->assertEquals($config = new ValidationConfiguration(new GroupSequence(array('Default', 'Then'))), unserialize(serialize($config)));
    }
}
