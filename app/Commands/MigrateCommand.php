<?php

declare(strict_types=1);

namespace App\Commands;

use Zephyr\Database\Connection;
use Zephyr\Database\Migration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateCommand extends Command
{
    protected static $defaultName = 'migrate';
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
        $this->setDescription('Bekleyen veritabanı geçişlerini çalıştırır.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->pdo = $this->connection->getPdo();
            
            // Adım 1: 'migrations' tablosu yoksa oluştur
            $this->ensureMigrationsTableExists();

            // Adım 2: Zaten çalıştırılmış migration'ları veritabanından al
            $runMigrations = $this->getRunMigrations();

            // Adım 3: Dosya sistemindeki tüm migration'ları al
            $allMigrations = $this->getAllMigrationFiles();
            
            if (empty($allMigrations)) {
                 $output->writeln('<info>Çalıştırılacak yeni migration bulunamadı. Her şey güncel.</info>');
                 return Command::SUCCESS;
            }

            // Adım 4: İkisini karşılaştırarak bekleyenleri (pending) bul
            $pendingMigrations = array_diff($allMigrations, $runMigrations);
            
            if (empty($pendingMigrations)) {
                 $output->writeln('<info>Çalıştırılacak yeni migration bulunamadı. Her şey güncel.</info>');
                 return Command::SUCCESS;
            }

            // Adım 5: Bekleyen migration'ları sırayla çalıştır
            $batch = $this->getNextBatchNumber();
            $output->writeln("<comment>Migration'lar çalıştırılıyor...</comment>");

            foreach ($pendingMigrations as $migrationFile) {
                $output->write("  <info>Çalıştırılıyor:</info> {$migrationFile}");

                // Dosyayı yükle ve 'up' metodunu çağır
                $migrationInstance = $this->loadMigrationFile($migrationFile);
                $migrationInstance->up();
                
                // Veritabanına logla
                $this->logMigration($migrationFile, $batch);
                
                $output->writeln(" ... <info>Başarılı.</info>");
            }

            $output->writeln("<info>Veritabanı geçişleri başarıyla tamamlandı.</info>");
            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $output->writeln("<error>Migration hatası: " . $e->getMessage() . "</error>");
            $output->writeln($e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    /**
     * 'migrations' tablosunun varlığını kontrol eder, yoksa oluşturur.
     */
    protected function ensureMigrationsTableExists(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL,
                batch INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB;
        ");
    }

    /**
     * Veritabanından daha önce çalıştırılmış migration'ların listesini alır.
     */
    protected function getRunMigrations(): array
    {
        $stmt = $this->pdo->query("SELECT migration FROM {$this->migrationsTable}");
        return $stmt ? $stmt->fetchAll(\PDO::FETCH_COLUMN) : [];
    }

    /**
     * 'database/migrations' dizinindeki tüm migration dosyalarını (sıralı) alır.
     */
    protected function getAllMigrationFiles(): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }
        
        $files = scandir($this->migrationsPath);
        $migrationFiles = array_filter($files, fn($file) => pathinfo($file, PATHINFO_EXTENSION) === 'php');
        
        // Zaman damgasına göre sıralamayı garanti et
        sort($migrationFiles); 
        
        return $migrationFiles;
    }

    /**
     * Bir migration dosyasını yükler ve sınıfı başlatır (instantiate).
     */
    protected function loadMigrationFile(string $filename): Migration
    {
        $path = "{$this->migrationsPath}/{$filename}";
        
        // include yerine return kullandığımız için dosya adı bir sınıf döndürmeli
        $migrationInstance = require_once $path;

        if (!$migrationInstance instanceof Migration) {
            throw new \RuntimeException("Migration dosyası ({$filename}) bir Migration nesnesi döndürmelidir.");
        }
        
        return $migrationInstance;
    }

    /**
     * Çalıştırılan migration'ı veritabanına kaydeder.
     */
    protected function logMigration(string $filename, int $batch): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO {$this->migrationsTable} (migration, batch) VALUES (?, ?)"
        );
        $stmt->execute([$filename, $batch]);
    }

    /**
     * Bir sonraki migration grubu (batch) numarasını alır.
     */
    protected function getNextBatchNumber(): int
    {
        $stmt = $this->pdo->query("SELECT MAX(batch) as max_batch FROM {$this->migrationsTable}");
        $maxBatch = $stmt ? $stmt->fetchColumn() : 0;
        return (int)$maxBatch + 1;
    }
}