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

use Symfony\Component\DependencyInjection\Attribute\AsCallable;

class Exporters
{
    public static int $instantiations = 0;

    public function __construct()
    {
        ++self::$instantiations;
    }

    #[AsCallable(tags: [['app.exporter' => ['format' => 'csv']]], lazy: ExporterInterface::class, target: 'csv')]
    public function exportCsv(array $rows): string
    {
        return 'csv:'.\count($rows);
    }

    #[AsCallable(tags: [['app.exporter' => ['format' => 'tsv']]], lazy: ExporterInterface::class)]
    public static function exportTsv(array $rows): string
    {
        return 'tsv:'.\count($rows);
    }

    #[AsCallable(target: 'html exporter')]
    public function exportHtml(array $rows): string
    {
        return 'html:'.\count($rows);
    }
}
