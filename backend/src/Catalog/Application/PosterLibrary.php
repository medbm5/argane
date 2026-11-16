<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Domain\Model\Poster;
use App\Shared\Domain\Exception\BusinessRuleException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

/**
 * Poster uploads from the Studio. Only raster photos (JPEG, PNG, WebP) up to 2 MB:
 * the type is read from the bytes, never trusted from the browser, and SVG is refused
 * because it can carry scripts.
 */
final readonly class PosterLibrary
{
    public const int MAX_BYTES = 2 * 1024 * 1024;
    private const array TYPES = [\IMAGETYPE_JPEG => 'image/jpeg', \IMAGETYPE_PNG => 'image/png', \IMAGETYPE_WEBP => 'image/webp'];
    private const int MAX_SIDE = 6000;

    public function __construct(
        private EntityManagerInterface $em,
        private Connection $connection,
        private ClockInterface $clock,
    ) {
    }

    public function upload(UploadedFile $file): Poster
    {
        // PHP's own upload limit (upload_max_filesize) is 2 MB too: past it the file arrives as an error.
        $tooLargeForPhp = \in_array($file->getError(), [\UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE], true);
        if (!$tooLargeForPhp && !$file->isValid()) {
            throw BusinessRuleException::because('POSTER_INVALID', 'Le fichier n’a pas été reçu. Réessayez.');
        }
        if ($tooLargeForPhp || (int) $file->getSize() > self::MAX_BYTES) {
            throw BusinessRuleException::because('POSTER_TOO_LARGE', 'L’affiche dépasse 2 Mo. Réduisez-la puis réessayez.', ['maxBytes' => self::MAX_BYTES]);
        }
        $bytes = (string) file_get_contents($file->getPathname());
        $info = '' === $bytes ? false : @getimagesizefromstring($bytes);
        $type = false === $info ? null : (self::TYPES[$info[2]] ?? null);
        if (null === $type || false === $info || $info[0] < 1 || $info[1] < 1 || max($info[0], $info[1]) > self::MAX_SIDE) {
            throw BusinessRuleException::because('POSTER_INVALID', 'Choisissez une image JPEG, PNG ou WebP.');
        }

        $poster = new Poster($type, $bytes, \strlen($bytes), $this->clock->now());
        $this->em->persist($poster);
        $this->em->flush();

        return $poster;
    }

    /**
     * Reads the bytes straight from the table, without loading an entity.
     *
     * @return array{mimeType: string, content: string}|null
     */
    public function find(string $id): ?array
    {
        if (!Uuid::isValid($id)) {
            return null;
        }
        $row = $this->connection->fetchAssociative(
            'SELECT mime_type, content FROM poster WHERE id = ?',
            [Uuid::fromString($id)->toBinary()],
            [ParameterType::BINARY],
        );

        return false === $row ? null : ['mimeType' => (string) $row['mime_type'], 'content' => (string) $row['content']];
    }
}
