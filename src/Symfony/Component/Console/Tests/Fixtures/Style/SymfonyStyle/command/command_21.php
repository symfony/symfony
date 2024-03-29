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

// Ensure texts with emojis don't make longer lines than expected
return function (InputInterface $input, OutputInterface $output) {
    $output = new SymfonyStyle($input, $output);
    $output->success('Lorem ipsum dolor sit amet');
    $output->success('Lorem ipsum dolor sit amet with one emoji 🎉');
    $output->success('Lorem ipsum dolor sit amet with so many of them 👩‍🌾👩‍🌾👩‍🌾👩‍🌾👩‍🌾');
};
