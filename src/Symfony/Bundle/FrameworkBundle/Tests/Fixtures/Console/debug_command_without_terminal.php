<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Bundle\FrameworkBundle\Command\DebugCommand;
use Symfony\Bundle\FrameworkBundle\Debug\DebugItem;
use Symfony\Bundle\FrameworkBundle\Debug\Section\AbstractDebugSection;
use Symfony\Component\Console\Application;

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

$section = new class extends AbstractDebugSection {
    public function getLabel(): string
    {
        return 'Container';
    }

    public function getShortLabel(): string
    {
        return 'DI';
    }

    public function describe(DebugItem $item, int $width): string
    {
        return $item->value;
    }

    protected function buildItems(): array
    {
        return [new DebugItem('service', 'http_client', 'http_client')];
    }
};

$application = new Application();
$application->addCommand(new DebugCommand(['container' => $section], ['container']));
$application->setDefaultCommand('debug', true);
$application->run();
