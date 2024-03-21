<?php

namespace Symfony\Component\Notifier\Bridge\Lox24;

enum ServiceCode: string
{
    case Sms = 'direct';
    case Voice = 'text2speech';
}
