<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

/**
 * @requires function Symfony\Component\Routing\Loader\Psr4DirectoryLoader::__construct
 */
final class Psr4RoutingTest extends AbstractAttributeRoutingTestCase
{
    protected function getTestCaseApp(): string
    {
        return 'Psr4Routing';
    }
}
