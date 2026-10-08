<?php
/**
 * Odepreny pristup: auth_require() bez potrebne role, csrf_check() bez platneho tokenu.
 *
 * Driv se volalo die('Nemáte oprávnění'), za Symfony to ale ukonci cely proces.
 * LegacyFrontController vyjimku zachyti a vrati 403 s hlaskou.
 */

namespace App\Legacy\lib;

class AccessDenied extends \RuntimeException
{
}
