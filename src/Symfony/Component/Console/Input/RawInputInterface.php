<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Input;

/**
 * Provides access to the original input arguments and options
 * before they are merged with default values, and allows
 * unparsing options back to their CLI representation.
 *
 * @author Théo FIDRY <theo.fidry@gmail.com>
 */
interface RawInputInterface extends InputInterface
{
    /**
     * Returns all the given arguments NOT merged with the default values.
     *
     * @return array<string|bool|int|float|array<string|bool|int|float|null>|null>
     */
    public function getRawArguments(): array;

    /**
     * Returns all the given options NOT merged with the default values.
     *
     * @return array<string|bool|int|float|array<string|bool|int|float|null>|null>
     */
    public function getRawOptions(): array;

    /**
     * Returns a stringified representation of the options passed to the command.
     *
     * The options are NOT escaped, as otherwise passing them to a Process
     * would result in them being escaped twice.
     *
     * @param string[]|null $optionNames Names of the options returned. If null, all options are returned.
     *                                   Requested options that either do not exist or were not passed
     *                                   (even if the option has a default value) will be ignored.
     *
     * @return list<string>
     */
    public function unparse(?array $optionNames = null): array;
}
