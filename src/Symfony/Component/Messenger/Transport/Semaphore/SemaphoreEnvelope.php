<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Semaphore;

/**
 * 
 * @author Cedrick Oka Baidai <okacedrick@gmail.com>
 * 
 */
class SemaphoreEnvelope
{
	/**
	 * @var int
	 */
	private $type;
	
	/**
	 * @var string
	 */
	private $body;
	
	/**
	 * @var array
	 */
	private $headers;
	
	public function __construct(int $type, string $body, array $headers = [])
	{
		$this->type = $type;
		$this->body = $body;
		$this->headers = $headers;
	}
	
	public function getType() :int
	{
		return $this->type;
	}
	
	public function getBody() :string {
		return $this->body;
	}
	
	public function getHeaders() :array {
		return $this->headers;
	}
}
