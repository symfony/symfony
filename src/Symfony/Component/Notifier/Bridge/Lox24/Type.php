<?php

namespace Symfony\Component\Notifier\Bridge\Lox24;

enum Type: string
{
    case Sms = 'sms';
    case Voice = 'voice';
}
