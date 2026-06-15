<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class SafeSqlRunner
{
    protected const MAX_ROWS = 200;
    protected const TIMEOUT_MS = 5000;

    /**
     * Keywords that must never appear in AI-generated SQL, checked as
     * whole words (case-insensitive) to avoid false positives on column
     * names that merely contain these substrings.
     */
    protected const FORBIDDEN_KEYWORDS = [
        'insert', 'update', 'delete', 'drop', 'alter', 'truncate', 'grant',
        'revoke', 'create', 'copy', 'call', 'execute', 'merge', 'vacuum',
        'reindex', 'cluster', 'comment', 'security', 'pg_sleep', 'lock',
        'into', 'do', 'listen', 'notify', 'unlisten', 'set',
    ];

    /**
     * Validate that the given SQL is a single, safe, read-only SELECT
     * statement. Throws RuntimeException with a human-readable reason if not.
     */
    public function validate(string $sql): string
    {
        $sql = trim($sql);
        $sql = rtrim($sql, "; \t\n\r");

        if ($sql === '') {
            throw new RuntimeException('Empty SQL.');
        }

        // Must not contain a second statement.
        if (str_contains($sql, ';')) {
            throw new RuntimeException('Multiple statements are not allowed.');
        }

        // Must start with SELECT or WITH ... SELECT
        if (!preg_match('/^\s*(SELECT|WITH)\s/i', $sql)) {
            throw new RuntimeException('Only SELECT queries are allowed.');
        }

        // For WITH queries, ensure the final statement contains SELECT
        if (preg_match('/^\s*WITH\s/i', $sql) && !preg_match('/\bSELECT\b/i', $sql)) {
            throw new RuntimeException('Only SELECT queries are allowed.');
        }

        foreach (self::FORBIDDEN_KEYWORDS as $kw) {
            if (preg_match('/(?<![a-zA-Z0-9_])' . $kw . '(?![a-zA-Z0-9_])/i', $sql)) {
                throw new RuntimeException("Disallowed keyword detected: {$kw}");
            }
        }

        // Auto-append a LIMIT if none present and no single-row aggregate pattern.
        if (!preg_match('/\bLIMIT\s+\d+/i', $sql)) {
            $sql .= ' LIMIT ' . self::MAX_ROWS;
        } else {
            // Cap any existing LIMIT to MAX_ROWS
            $sql = preg_replace_callback('/\bLIMIT\s+(\d+)/i', function ($m) {
                return 'LIMIT ' . min((int) $m[1], self::MAX_ROWS);
            }, $sql);
        }

        return $sql;
    }

    /**
     * Run the SQL in a read-only transaction with a statement timeout.
     * Returns an array of associative-array rows.
     *
     * @throws RuntimeException on validation failure or query error
     */
    public function run(string $sql): array
    {
        $safeSql = $this->validate($sql);

        return DB::transaction(function () use ($safeSql) {
            DB::statement('SET TRANSACTION READ ONLY');
            DB::statement('SET LOCAL statement_timeout = ' . self::TIMEOUT_MS);

            $rows = DB::select($safeSql);

            return array_map(fn ($row) => (array) $row, $rows);
        });
    }
}
