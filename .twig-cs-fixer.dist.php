<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$ruleset = new TwigCsFixer\Ruleset\Ruleset();
$ruleset->addStandard(new TwigCsFixer\Standard\TwigCsFixer());

$finder = new TwigCsFixer\File\Finder();
$finder->in('src/');
$finder->exclude('Symfony/Contracts/');
$finder->exclude('Fixtures');

$config = new TwigCsFixer\Config\Config();
$config->setCacheFile('.twig-cs-fixer.cache');
$config->setRuleset($ruleset);
$config->setFinder($finder);

return $config;
