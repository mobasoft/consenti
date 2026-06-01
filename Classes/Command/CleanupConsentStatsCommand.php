<?php

declare(strict_types=1);

namespace Mobasoft\Consenti\Command;

use Doctrine\DBAL\ParameterType;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[AsCommand(
    name: 'consenti:consent-stats:cleanup',
    description: 'Remove old or all records from consent stats table.'
)]
final class CleanupConsentStatsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Delete consent stats with last_seen older than N days', '365')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Delete all consent stats records')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show number of affected rows without deleting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            return $this->executeInternal($input, $output);
        } catch (\Throwable $exception) {
            $output->writeln('Could not run cleanup: ' . $exception->getMessage());
            return Command::SUCCESS;
        }
    }

    private function executeInternal(InputInterface $input, OutputInterface $output): int
    {
        $deleteAll = (bool)$input->getOption('all');
        $dryRun = (bool)$input->getOption('dry-run');
        $days = max(0, (int)$input->getOption('days'));
        $threshold = time() - ($days * 86400);

        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_consenti_domain_model_consent_stat');
        if (!$connection->createSchemaManager()->tablesExist(['tx_consenti_domain_model_consent_stat'])) {
            $output->writeln('Consent stats table does not exist yet. Run database schema update first.');
            return Command::SUCCESS;
        }

        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder->count('uid')->from('tx_consenti_domain_model_consent_stat');
        if (!$deleteAll) {
            $queryBuilder->where(
                $queryBuilder->expr()->lt('last_seen', $queryBuilder->createNamedParameter($threshold, ParameterType::INTEGER))
            );
        }
        $count = (int)$queryBuilder->executeQuery()->fetchOne();
        if ($dryRun) {
            $output->writeln(sprintf('Would delete %d record(s).', $count));
            return Command::SUCCESS;
        }
        if ($count === 0) {
            $output->writeln('No consent stats records to delete.');
            return Command::SUCCESS;
        }

        if ($deleteAll) {
            $deleted = $connection->createQueryBuilder()->delete('tx_consenti_domain_model_consent_stat')->executeStatement();
        } else {
            $deleteQb = $connection->createQueryBuilder();
            $deleted = $deleteQb
                ->delete('tx_consenti_domain_model_consent_stat')
                ->where($deleteQb->expr()->lt('last_seen', $deleteQb->createNamedParameter($threshold, ParameterType::INTEGER)))
                ->executeStatement();
        }

        $output->writeln(sprintf('Deleted %d record(s).', (int)$deleted));
        return Command::SUCCESS;
    }
}
