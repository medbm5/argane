<?php

declare(strict_types=1);

namespace App\Integration\Api;

use App\Integration\Application\SheetsSync;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:sheets:sync', description: 'Rewrite the Ventes and Synthèse tabs of the Google Sheet now')]
final class SheetsSyncCommand extends Command
{
    public function __construct(private readonly SheetsSync $sync)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$this->sync->status()['configured']) {
            $io->warning('Google Sheets is not configured (GOOGLE_SHEETS_SPREADSHEET_ID, GOOGLE_SERVICE_ACCOUNT_JSON_BASE64). See INSTALL.md.');

            return Command::FAILURE;
        }
        $this->sync->sync();
        $status = $this->sync->status();
        $io->success(sprintf('%d ticket rows written to %s', $status['rows'], $status['url']));

        return Command::SUCCESS;
    }
}
