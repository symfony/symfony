<?php

namespace Symfony\Component\Notifier\Bridge\Lox24;

enum VoiceLanguage: string
{
    case German = 'de';
    case English = 'en';
    case Spanish = 'es';
    case French = 'fr';
    case Italian = 'it';
    case Auto = 'auto';
}
