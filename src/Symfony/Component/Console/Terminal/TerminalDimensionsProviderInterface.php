<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Terminal;

interface TerminalDimensionsProviderInterface
{
	/**
	 * Tries to figure out the terminal dimensions based on the current environment.
	 *
	 * @return int[] Array containing width and height
	 */
	public function getTerminalDimensions();

	/**
	 * Tries to figure out the terminal width in which this application runs.
	 *
	 * @return int|null
	 */
	public function getTerminalWidth();

	/**
	 * Tries to figure out the terminal height in which this application runs.
	 *
	 * @return int|null
	 */
	public function getTerminalHeight();

	/**
	 * Sets terminal dimensions.
	 *
	 * Can be useful to force terminal dimensions for functional tests.
	 *
	 * @param int $width
	 * @param int $height
	 */
	public function setTerminalDimensions($width, $height);
}
