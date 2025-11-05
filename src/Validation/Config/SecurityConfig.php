<?php

declare(strict_types=1);

namespace Zephyr\Validation\Config;

class SecurityConfig
{
    /**
     * Güvenli karakter setleri
     */
    public const CHAR_SETS = [
        'latin' => '/^[\p{Latin}]+$/u',
        'alphanumeric' => '/^[a-zA-Z0-9]+$/',
        'numeric' => '/^[0-9]+$/',
        'alpha' => '/^[a-zA-Z]+$/'
    ];

    /**
     * Zararlı dosya uzantıları
     */
    public const DANGEROUS_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'phtml',
        'exe', 'bat', 'cmd', 'sh', 'pl', 'cgi',
        'aspx', 'asp', 'jsp',
        'js', 'vbs'
    ];
}