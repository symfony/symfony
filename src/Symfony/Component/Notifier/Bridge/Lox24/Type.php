<?php

namespace Symfony\Component\Notifier\Bridge\Lox24;

enum Type: string
{
    case Sms = 'sms';
    case Voice = 'voice';

    public function getServiceCode(): string
    {
        return match ($this) {
            self::Sms => 'direct',
            self::Voice => 'text2speech'
        };
    }

}
