<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\DataCollector;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\DataCollector\DataCollectorExtension;
use Symfony\Component\Form\Extension\DataCollector\FormDataCollector;
use Symfony\Component\Form\Extension\DataCollector\FormDataExtractor;
use Symfony\Component\Form\Extension\DataCollector\Type\DataCollectorTypeExtension;

class DataCollectorExtensionTest extends TestCase
{
    /**
     * @var DataCollectorExtension
     */
    private $extension;

    protected function setUp(): void
    {
        $this->extension = new DataCollectorExtension(new FormDataCollector(new FormDataExtractor()));
    }

    public function testLoadTypeExtensions()
    {
        $typeExtensions = $this->extension->getTypeExtensions('Symfony\Component\Form\Extension\Core\Type\FormType');

        $this->assertIsArray($typeExtensions);
        $this->assertCount(1, $typeExtensions);
        $this->assertInstanceOf(DataCollectorTypeExtension::class, array_shift($typeExtensions));
    }
}
