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

// Ensure that questions have the expected outputs
return function (InputInterface $input, OutputInterface $output) {
    $output = new SymfonyStyle($input, $output);
    $stream = fopen('php://memory', 'r+', false);

    fwrite($stream, "Foo\nBar\nBaz");
    rewind($stream);
    $input->setStream($stream);

    $output->ask('What\'s your name?');
    $output->ask('How are you?');
    $output->ask('Where do you come from?');
};
