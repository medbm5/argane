<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Catalog\Domain\Model\Event;
use Symfony\Component\Uid\Uuid;

interface EventRepository
{
    public function save(Event $event): void;

    public function find(Uuid $id): ?Event;

    public function findBySlug(string $slug): ?Event;

    public function slugExists(string $slug): bool;
}
