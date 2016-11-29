<?php

namespace Symfony\Bridge\Doctrine\Tests\DataCollector;

/**
 * A class for testing __toString method behaviour. It's __toString returns a value, that was passed into constructor.
 *
 * @package Symfony\Bridge\Doctrine\Tests\DataCollector
 */
class StringRepresentableClass
{
	/**
	 * @var string
	 */
	private $representation;

	/**
	 * CustomStringableClass constructor.
	 *
	 * @param string $representation
	 */
	public function __construct($representation)
	{
		$this->representation = $representation;
	}

	public function __toString()
	{
		return $this->representation;
	}
}
