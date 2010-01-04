<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../lib/vendor/lime/LimeAutoloader.php';
LimeAutoloader::register();

$h = new LimeTestSuite(array(
  'force_colors' => isset($argv) && in_array('--color', $argv),
  'verbose'      => isset($argv) && in_array('--verbose', $argv),
));
$h->base_dir = realpath(__DIR__.'/..');

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../unit'), RecursiveIteratorIterator::LEAVES_ONLY) as $file)
{
  if (preg_match('/Test\.php$/', $file))
  {
    $h->register($file->getRealPath());
  }
}

exit($h->run() ? 0 : 1);
