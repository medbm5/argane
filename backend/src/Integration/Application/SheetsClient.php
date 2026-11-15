<?php

declare(strict_types=1);

namespace App\Integration\Application;

/** Port to a spreadsheet: whole-tab rewrites only. */
interface SheetsClient
{
    public function isConfigured(): bool;

    /** Public link of the spreadsheet (for the Studio), null when not configured. */
    public function url(): ?string;

    /**
     * Replaces the whole content of a tab (cleared, then written from A1), creating the
     * tab if it does not exist.
     *
     * @param list<list<string|int|float|null>> $rows header row first
     */
    public function replaceTab(string $tab, array $rows): void;
}
