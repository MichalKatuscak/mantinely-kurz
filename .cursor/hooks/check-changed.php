<?php

declare(strict_types=1);

/*
 * Hook postToolUse pro Cursor (matcher Write): spustí make check-changed.
 *
 * Cursor agentovi nepředá stderr ani kód 2 z postToolUse. Chyby mu vrátí jen pole
 * additional_context ve výstupu hooku, proto tenhle obal kolem stejného cíle v Makefile.
 */

exec('make --no-print-directory check-changed 2>&1', $output, $status);

if ($status !== 0) {
    echo json_encode(
        ['additional_context' => "make check-changed hlásí chyby:\n".implode("\n", $output)],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    ), "\n";
}

exit(0);
