<?php

declare(strict_types=1);

namespace WG\QrBill\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1711300000CreateDunningTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1711300000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `wg_dunning` (
                `id`            BINARY(16)      NOT NULL,
                `order_id`      BINARY(16)      NOT NULL,
                `order_version_id` BINARY(16)   NOT NULL,
                `level`         INT(11)         NOT NULL DEFAULT 1,
                `fee`           DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
                `total_amount`  DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
                `due_date`      DATETIME(3)     NULL,
                `sent_at`       DATETIME(3)     NULL,
                `notes`         LONGTEXT        NULL,
                `created_at`    DATETIME(3)     NOT NULL,
                `updated_at`    DATETIME(3)     NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk.wg_dunning.order_id` FOREIGN KEY (`order_id`, `order_version_id`)
                    REFERENCES `order` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
                INDEX `idx.wg_dunning.order_id` (`order_id`),
                INDEX `idx.wg_dunning.level` (`level`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
