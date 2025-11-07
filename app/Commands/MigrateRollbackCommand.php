<?php

declare(strict_types=1);

namespace App\Commands;

use Zephyr\Database\Connection;
use Zephyr\Database\Migration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateRollbackCommand extends Command
{
    protected static $defaultName = 'migrate:rollback';
    protected Connection $connection;
    protected ?\PDO $pdo = null;
    protected string $migrationsTable = 'migrations';
    protected string $migrationsPath;

    public function __construct(Connection $connection)
    {
        parent::__construct();
        $this->connection = $connection;
        $this->migrationsPath = base_path('database/migrations');
    }

    protected function configure(): void
    {
        $this->setDescription('En son çalıştırılan veritabanı geçiş grubunu geri alır.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->pdo = $this->connection->getPdo();

            // Adım 1: Son 'batch' numarasını bul
            $lastBatch = $this->getLastBatchNumber();

            if ($lastBatch === 0) {
                $output->writeln('<info>Geri alınacak migration bulunamadı.</info>');
                return Command::SUCCESS;
            }

            // Adım 2: Bu batch'e ait migration'ları al (ters sırada)
            $migrationsToRollback = $this->getMigrationsInBatch($lastBatch);

            if (empty($migrationsToRollback)) {
                $output->writeln('<info>Geri alınacak migration bulunamadı.</info>');
                return Command::SUCCESS;
            }
            
            $output->writeln("<comment>Son migration grubu (Batch {$lastBatch}) geri alınıyor...</comment>");

            // Adım 3: Migration'ları (ters sırada) çalıştır
            foreach ($migrationsToRollback as $migrationFile) {
                $output->write("  <info>Geri alınıyor:</info> {$migrationFile}");

                // Dosyayı yükle ve 'down' metodunu çağır
                $migrationInstance = $this->loadMigrationFile($migrationFile);
                $migrationInstance->down();
                
                // Veritabanından log kaydını sil
                $this->removeMigrationLog($migrationFile);
                
                $output->writeln(" ... <info>Başarılı.</info>");
            }

            $output->writeln("<info>Migration'lar başarıyla geri alındı.</info>");
            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $output->writeln("<error>Rollback hatası: " . $e->getMessage() . "</error>");
            $output->writeln($e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    /**
     * En yüksek batch numarasını alır.
     */
    protected function getLastBatchNumber(): int
    {
        $stmt = $this->pdo->query("SELECT MAX(batch) as max_batch FROM {$this->migrationsTable}");
        $maxBatch = $stmt ? $stmt->fetchColumn() : 0;
        return (int)$maxBatch;
    }

    /**
     * Belirli bir batch'teki migration'ları (çalıştırılma sırasının tersine) alır.
     */
    protected function getMigrationsInBatch(int $batch): array
    {
        $stmt = $this->pdo->prepare("SELECT migration FROM {$this->migrationsTable} WHERE batch = ? ORDER BY migration DESC");
        $stmt->execute([$batch]);
        return $stmt ? $stmt->fetchAll(\PDO::FETCH_COLUMN) : [];
    }

    /**
     * Bir migration dosyasını yükler ve sınıfı başlatır.
     */
    protected function loadMigrationFile(string $filename): Migration
    {
        $path = "{$this->migrationsPath}/{$filename}";
        
        if (!file_exists($path)) {
            throw new \RuntimeException("Migration dosyası bulunamadı: {$filename}");
        }

        $migrationInstance = require_once $path;

        if (!$migrationInstance instanceof Migration) {
            throw new \RuntimeException("Migration dosyası ({$filename}) bir Migration nesnesi döndürmelidir.");
        }
        
        return $migrationInstance;
    }

    /**
     * Çalıştırılan migration'ın kaydını veritabanından siler.
     */
    protected function removeMigrationLog(string $filename): void
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM {$this->migrationsTable} WHERE migration = ?"
        );
        $stmt->execute([$filename]);
    }
}