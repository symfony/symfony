<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\RegisterCallableServicesPass;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\DependencyInjection\Attribute\Target;

final class ExporterConsumer
{
    /**
     * @param iterable<string, ExporterInterface> $exporters
     */
    public function __construct(
        #[AutowireIterator('app.exporter', indexAttribute: 'format')]
        public iterable $exporters,
        #[Target('csv')]
        public ExporterInterface $csvExporter,
        #[Target('html exporter')]
        public \Closure $htmlExporter,
    ) {
    }
}
