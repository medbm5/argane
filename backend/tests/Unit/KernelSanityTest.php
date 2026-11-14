<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Kernel;
use PHPUnit\Framework\TestCase;

final class KernelSanityTest extends TestCase
{
    public function testKernelBootsInTestEnvironment(): void
    {
        $kernel = new Kernel('test', false);
        $kernel->boot();

        self::assertSame('test', $kernel->getEnvironment());

        $kernel->shutdown();
    }
}
