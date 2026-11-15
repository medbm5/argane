<?php

declare(strict_types=1);

namespace App\Reporting\Application\Query;

use App\Reporting\Application\Query\View\SalesPageView;

interface SalesQueries
{
    public function page(SalesFilter $filter): SalesPageView;

    /**
     * One row per ticket of the filtered orders, for CSV export (streamed).
     *
     * @return iterable<array<string, string|int|null>>
     */
    public function ticketRows(SalesFilter $filter): iterable;
}
