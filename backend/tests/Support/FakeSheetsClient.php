<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Integration\Application\SheetsClient;
use RuntimeException;

/** In-memory spreadsheet: tabs keep the last rows written; can pretend to be down. */
final class FakeSheetsClient implements SheetsClient
{
    /** @var array<string, list<list<string|int|float|null>>> */
    public array $tabs = [];
    public int $writes = 0;
    public bool $configured = true;
    public bool $down = false;

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function url(): ?string
    {
        return $this->configured ? 'https://docs.google.com/spreadsheets/d/fake' : null;
    }

    public function replaceTab(string $tab, array $rows): void
    {
        if ($this->down) {
            throw new RuntimeException('Google is unreachable');
        }
        $this->tabs[$tab] = $rows;
        ++$this->writes;
    }
}
