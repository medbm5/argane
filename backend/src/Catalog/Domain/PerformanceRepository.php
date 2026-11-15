<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Catalog\Domain\Model\Event;
use App\Catalog\Domain\Model\Performance;
use Symfony\Component\Uid\Uuid;

interface PerformanceRepository
{
    public function save(Performance $performance): void;

    public function find(Uuid $id): ?Performance;

    /**
     * @return list<Performance>
     */
    public function ofEvent(Event $event): array;
}
