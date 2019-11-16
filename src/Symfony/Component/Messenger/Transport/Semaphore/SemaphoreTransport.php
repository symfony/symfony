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

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * 
 * @author Cedrick Oka Baidai <okacedrick@gmail.com>
 * 
 */
class SemaphoreTransport implements TransportInterface
{
	/**
	 * @var Connection $connection
	 */
	private $connection;
	
	/**
	 * @var SerializerInterface $serializer
	 */
	private $serializer;
	
	/**
	 * @var ReceiverInterface $receiver
	 */
	private $receiver;
	
	/**
	 * @var SenderInterface $sender
	 */
	private $sender;
	
	public function __construct(Connection $connection, SerializerInterface $serializer = null)
	{
		$this->connection = $connection;
		$this->serializer = $serializer ?? new PhpSerializer();
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface::get()
	 */
	public function get() :iterable
	{
		return $this->getReceiver()->get();
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface::ack()
	 */
	public function ack(Envelope $envelope) :void
	{
		$this->getReceiver()->ack($envelope);
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface::reject()
	 */
	public function reject(Envelope $envelope) :void
	{
		$this->getReceiver()->reject($envelope);
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Symfony\Component\Messenger\Transport\Sender\SenderInterface::send()
	 */
	public function send(Envelope $envelope) :Envelope
	{
		return $this->getSender()->send($envelope);
	}
	
	private function getReceiver() :ReceiverInterface
	{
		if (null === $this->receiver) {
			$this->receiver = new SemaphoreReceiver($this->connection, $this->serializer);
		}
		
		return $this->receiver;
	}
	
	private function getSender() :SenderInterface
	{
		if (null === $this->sender) {
			$this->sender = new SemaphoreSender($this->connection, $this->serializer);
		}
		
		return $this->sender;
	}
}

