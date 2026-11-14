<?php

declare(strict_types=1);

namespace App\Tests\Unit\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Enforces the dependency rules described in docs/ARCHITECTURE.md:
 *  - a module never imports another module's Infrastructure or Api layer;
 *  - the Domain layer never depends on HTTP, Doctrine DBAL or the framework bundle.
 */
final class ModuleBoundariesTest extends TestCase
{
    private const string SRC = __DIR__.'/../../../src';

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function phpFiles(): iterable
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::SRC, RecursiveDirectoryIterator::SKIP_DOTS));
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ('php' !== $file->getExtension()) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), \strlen(self::SRC) + 1));
            $parts = explode('/', $relative);
            if (\count($parts) < 3) {
                continue; // Kernel.php
            }
            yield $relative => [$relative, $parts[0], $parts[1]];
        }
    }

    #[DataProvider('phpFiles')]
    public function testRespectsModuleBoundaries(string $relative, string $module, string $layer): void
    {
        $source = (string) file_get_contents(self::SRC.'/'.$relative);
        preg_match_all('/^use\s+App\\\\(\w+)\\\\(\w+)\\\\/m', $source, $matches, \PREG_SET_ORDER);

        foreach ($matches as [, $otherModule, $otherLayer]) {
            if ($otherModule === $module) {
                continue;
            }
            self::assertNotContains(
                $otherLayer,
                ['Infrastructure', 'Api'],
                sprintf('%s must not import %s\\%s (use its Application or Domain layer).', $relative, $otherModule, $otherLayer),
            );
        }

        if ('Domain' === $layer) {
            self::assertDoesNotMatchRegularExpression(
                '/^use\s+(Symfony\\\\Component\\\\HttpFoundation|Symfony\\\\Bundle|Doctrine\\\\DBAL\\\\Connection)/m',
                $source,
                sprintf('%s is in a Domain layer and must stay framework-free.', $relative),
            );
        }
    }
}
