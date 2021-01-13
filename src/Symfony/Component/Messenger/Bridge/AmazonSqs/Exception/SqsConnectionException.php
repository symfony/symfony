<?php

namespace Symfony\Component\Messenger\Bridge\AmazonSqs\Exception;

use Symfony\Component\Messenger\Exception\RecoverableExceptionInterface;
use Symfony\Component\Messenger\Exception\TransportException;

class SqsConnectionException extends TransportException implements RecoverableExceptionInterface
{}
