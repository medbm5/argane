<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * An uploaded poster image, kept in the database: the free hosting plan has no
 * persistent disk, and a demo does not need object storage (ADR-014). Events point at
 * it through their poster path, `/api/posters/{id}`.
 */
#[ORM\Entity]
#[ORM\Table(name: 'poster')]
class Poster
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    /**
     * @param string|resource $content the image bytes (Doctrine may read them back as a stream;
     *                                 PosterLibrary reads them with DBAL instead)
     */
    public function __construct(
        #[ORM\Column(length: 32)]
        private string $mimeType,
        #[ORM\Column(type: Types::BLOB)]
        private mixed $content,
        #[ORM\Column(type: Types::INTEGER)]
        private int $byteSize,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private DateTimeImmutable $createdAt,
    ) {
        $this->id = Uuid::v7();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function path(): string
    {
        return '/api/posters/'.$this->id->toRfc4122();
    }

    public function mimeType(): string
    {
        return $this->mimeType;
    }

    public function byteSize(): int
    {
        return $this->byteSize;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
