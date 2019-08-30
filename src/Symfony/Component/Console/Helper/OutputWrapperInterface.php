<?php declare(strict_types=1);

namespace Symfony\Component\Console\Helper;

interface OutputWrapperInterface
{
    const TAG_OPEN_REGEX = '[a-z](?:[^\\\\<>]*+ | \\\\.)*';
    const TAG_CLOSE_REGEX = '[a-z][^<>]*+';

    public function wrap(string $text, int $width, string $break = "\n"): string;
}
