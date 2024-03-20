<?php

namespace Symfony\Component\Notifier\Bridge\LOX24;

enum Type: string
{
    case Sms = 'sms';
    case Voice = 'voice';
}
