<?php

declare(strict_types=1);

/*
 * Hook PreToolUse pro Edit a Write: test, který v repozitáři byl na začátku úlohy,
 * agent neupraví ani nepřepíše. Nový test založit a opravit smí, i když ho mezitím
 * commitnul.
 *
 * Začátek úlohy je výchozí tag cvičení (mNN-start), stejně jako INFECTION_BASE
 * v Makefile. Chráněný je soubor pod tests/, který je ve stromu toho tagu. Když tag
 * nejde zjistit, chráněný je každý soubor pod tests/, který git sleduje, a když nejde
 * zjistit ani to, každý existující soubor pod tests/.
 *
 * Oprávnění v .claude/settings.json tohle rozlišit neumí. Pravidlo Edit(...) platí
 * pro všechny nástroje, které zapisují do souborů, i pro Write, takže zákaz
 * Edit(./tests/**) by zakázal i nový test. Hlídací testy (CsrfTest, AuthRequireTest,
 * SecurityFunctionsTest, tests/PHPStan) a konfiguraci kontrol proto chrání deny
 * v oprávněních, ostatní existující testy tenhle hook.
 *
 * Kód 2 = nástroj se nespustí a text ze stderr dostane agent jako odpověď.
 */

/**
 * Absolutní cesta bez symbolických odkazů i pro soubor, který ještě neexistuje
 * (realpath vrací false): realpath nejbližší existující nadřazené složky a za ní
 * zbytek cesty.
 */
function resolvePath(string $path): string|false
{
    $real = realpath($path);
    if ($real !== false) {
        return rtrim($real, DIRECTORY_SEPARATOR);
    }

    $parent = dirname($path);
    $resolved = $parent === $path ? false : resolvePath($parent);
    if ($resolved === false) {
        return false;
    }

    $segment = basename($path);

    return match ($segment) {
        '..' => dirname($resolved),
        '.', '' => $resolved,
        default => $resolved.DIRECTORY_SEPARATOR.$segment,
    };
}

/**
 * @param list<string> $args
 *
 * @return array{int, string} návratový kód gitu (-1 když git nejde spustit) a stdout
 */
function git(string $root, array $args): array
{
    $process = proc_open(
        ['git', '--literal-pathspecs', '-C', $root, ...$args],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
    );
    if ($process === false) {
        return [-1, ''];
    }
    $stdout = (string) stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($process), trim($stdout)];
}

$input = json_decode((string) file_get_contents('php://stdin'), true);
$path = is_array($input) && is_array($input['tool_input'] ?? null) ? ($input['tool_input']['file_path'] ?? null) : null;
if (!is_string($path) || $path === '') {
    exit(0);
}

$projectDir = getenv('CLAUDE_PROJECT_DIR');
$root = realpath(is_string($projectDir) && $projectDir !== '' ? $projectDir : (string) getcwd());
$file = resolvePath($path); // nový soubor: realpath složky, ve které vznikne
if ($root === false || $file === false || !str_starts_with($file, $root.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR)) {
    exit(0);
}
$relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file, strlen($root) + 1));

[$status, $tag] = git($root, ['describe', '--tags', '--abbrev=0', '--match', 'm[0-9][0-9]-start']);
if ($status === 0 && $tag !== '') {
    [$status] = git($root, ['cat-file', '-e', $tag.':./'.$relative]);
    $protected = $status === 0;
} else {
    [$status] = git($root, ['ls-files', '--error-unmatch', '--', $relative]); // 0 sleduje, 1 nesleduje
    $protected = $status === 0 || ($status !== 1 && file_exists($file));
}

if ($protected) {
    fwrite(STDERR, sprintf(
        "Existující test %s agent neupravuje. Nový test založ jako nový soubor. Když je potřeba změnit existující test, navrhni změnu i s důvodem a udělá ji člověk.\n",
        $relative,
    ));
    exit(2);
}

exit(0);
