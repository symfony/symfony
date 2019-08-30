<?php declare(strict_types=1);

namespace Symfony\Component\Console\Helper;

interface OutputWrapperInterface
{
    const TAG_INNER_REGEX = '[a-z][^<>]*+';

    public function wrap(string $text, int $width, string $break = "\n"): string;
}
