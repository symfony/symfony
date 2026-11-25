<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Layout;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Layout\ContainerMetrics;

class ContainerMetricsTest extends TestCase
{
    public function testFingerprintRetainsFractionalChangesRegardlessOfSerializePrecision(): void
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><div></div>');
        $node = $document->querySelector('div');
        $metrics = new \SplObjectStorage();
        $previous = ini_set('serialize_precision', '3');
        try {
            $metrics[$node] = ['width' => 100.01];
            $first = ContainerMetrics::fingerprint($metrics);
            $metrics[$node] = ['width' => 100.02];
            self::assertNotSame($first, ContainerMetrics::fingerprint($metrics));
            $metrics[$node] = ['width' => 100.01];
            self::assertSame($first, ContainerMetrics::fingerprint($metrics));
        } finally {
            ini_set('serialize_precision', $previous);
        }
    }
}
