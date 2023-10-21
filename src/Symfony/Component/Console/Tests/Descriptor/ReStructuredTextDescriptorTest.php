<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Descriptor;

use Symfony\Component\Console\Descriptor\ReStructuredTextDescriptor;
use Symfony\Component\Console\Tests\Fixtures\DescriptorApplicationMbString;
use Symfony\Component\Console\Tests\Fixtures\DescriptorCommandMbString;

class ReStructuredTextDescriptorTest extends AbstractDescriptorTestCase
{
    public static function getDescribeCommandTestData()
    {
        return self::getDescriptionTestData(array_merge(
            ObjectsProvider::getCommands(),
            ['command_mbstring' => new DescriptorCommandMbString()]
        ));
    }

    public static function getDescribeApplicationTestData()
    {
        return self::getDescriptionTestData(array_merge(
            ObjectsProvider::getApplications(),
            ['application_mbstring' => new DescriptorApplicationMbString()]
        ));
    }

    protected function getDescriptor()
    {
        return new ReStructuredTextDescriptor();
    }

    protected static function getFormat()
    {
        return 'rst';
    }
}
