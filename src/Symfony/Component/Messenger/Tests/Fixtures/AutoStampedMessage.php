<?php

namespace Symfony\Component\Messenger\Tests\Fixtures;

use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ValidationStamp;

#[DelayStamp(123)]
#[ValidationStamp(['Default', 'Extra'])]
class AutoStampedMessage
{
}
