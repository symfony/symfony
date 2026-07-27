<?php

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// Ensure setBlockStyle(BLOCK_STYLE_OUTLINE) makes success/error/warning/note/info/caution
// render as outline blocks, and switching back to BLOCK_STYLE_DEFAULT restores the default rendering.
return function (InputInterface $input, OutputInterface $output): int {
    $io = new SymfonyStyle($input, $output);
    $io->setBlockStyle(SymfonyStyle::BLOCK_STYLE_OUTLINE);
    $io->success('Success message.');
    $io->error('Error message.');
    $io->warning('Warning message.');
    $io->note('Note message.');
    $io->info('Info message.');
    $io->caution('Caution message.');

    $io->setBlockStyle(SymfonyStyle::BLOCK_STYLE_DEFAULT);
    $io->success('Back to normal.');

    return 0;
};
