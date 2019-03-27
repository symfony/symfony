<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Extractor;

use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy;

/**
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
class ClassCollectionExtractorTest extends TestCase
{
    public function testExtractor()
    {
        $extractor = new ClassCollectionExtractor([Dummy::class]);
        $classes = $extractor->getClasses();

        $this->assertContains(Dummy::class, $classes);
    }
}
