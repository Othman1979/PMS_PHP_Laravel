<?php

namespace App\Services;

use Generator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Pure-PHP SQL dump (no mysqldump binary needed on shared hosting).
 * Produces DROP TABLE + CREATE TABLE + INSERT statements that phpMyAdmin can import.
 */
class DatabaseBackup
{
    private const CHUNK = 200;

    public function fileName(): string
    {
        return 'pms-backup-'.now()->format('Ymd-Hi').'.sql';
    }

    /** @return Generator<int, string> */
    public function stream(): Generator
    {
        $conn = DB::connection();
        $driver = $conn->getDriverName();
        $pdo = $conn->getPdo();
        $grammar = $conn->getQueryGrammar();

        yield '-- PMS backup '.now()->toDateTimeString()." ({$driver})\n";
        if ($driver === 'mysql') {
            yield "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
        }

        foreach ($this->tables($driver) as $table => $create) {
            $wrapped = $grammar->wrapTable($table);
            yield "DROP TABLE IF EXISTS {$wrapped};\n{$create};\n\n";

            $columns = null;
            $rows = [];
            foreach ($conn->table($table)->cursor() as $row) {
                $row = (array) $row;
                $columns ??= implode(', ', array_map(fn (string $c) => $grammar->wrap($c), array_keys($row)));
                $rows[] = '('.implode(', ', array_map(fn (mixed $v) => $this->literal($pdo, $v), $row)).')';
                if (count($rows) >= self::CHUNK) {
                    yield "INSERT INTO {$wrapped} ({$columns}) VALUES\n".implode(",\n", $rows).";\n";
                    $rows = [];
                }
            }
            if ($rows !== []) {
                yield "INSERT INTO {$wrapped} ({$columns}) VALUES\n".implode(",\n", $rows).";\n";
            }
            yield "\n";
        }

        if ($driver === 'mysql') {
            yield "SET FOREIGN_KEY_CHECKS=1;\n";
        }
    }

    /** Writes the dump to disk and returns the file path. */
    public function toFile(string $directory): string
    {
        File::ensureDirectoryExists($directory);
        $path = rtrim($directory, '/').'/'.$this->fileName();
        $handle = fopen($path, 'wb');
        foreach ($this->stream() as $chunk) {
            fwrite($handle, $chunk);
        }
        fclose($handle);

        return $path;
    }

    /** Deletes the oldest backups in `$directory`, keeping the newest `$keep`. */
    public function prune(string $directory, int $keep): int
    {
        if (! File::isDirectory($directory)) {
            return 0;
        }
        $files = collect(File::files($directory))
            ->filter(fn ($f) => Str::startsWith($f->getFilename(), 'pms-backup-') && $f->getExtension() === 'sql')
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->values();
        $old = $files->slice(max(0, $keep));
        $old->each(fn ($f) => File::delete($f->getPathname()));

        return $old->count();
    }

    /** @return array<string, string> table => CREATE statement */
    private function tables(string $driver): array
    {
        if ($driver === 'sqlite') {
            return collect(DB::select("SELECT name, sql FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"))
                ->mapWithKeys(fn ($r) => [$r->name => $r->sql])->all();
        }

        $tables = [];
        foreach (DB::select('SHOW TABLES') as $row) {
            $name = (string) array_values((array) $row)[0];
            $create = (array) DB::selectOne("SHOW CREATE TABLE `{$name}`");
            $tables[$name] = (string) ($create['Create Table'] ?? $create['Create View'] ?? '');
        }

        return $tables;
    }

    private function literal(\PDO $pdo, mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_int($value) || is_float($value) => (string) $value,
            is_bool($value) => $value ? '1' : '0',
            default => $pdo->quote((string) $value),
        };
    }
}
