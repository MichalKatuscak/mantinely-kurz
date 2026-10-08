<?php

declare(strict_types=1);

namespace App\Tests\PHPStan\Data;

// Varianta, kterou agent zkouší, když pravidlo nejde splnit: chybu umlčí komentářem.
function storno_umlcene($id)
{
    global $db;

    // @phpstan-ignore mantinely.sqlConcatenation
    return $db->one("SELECT * FROM orders WHERE id = '" . $id . "'");
}
