<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// Ensure that closing tag is applied once
return function (InputInterface $input, OutputInterface $output) {
    $output->setDecorated(true);
    $output = new SymfonyStyle($input, $output);
    $output->write('<question>do you want <comment>something</>');
    $output->writeln('?</>');
};
