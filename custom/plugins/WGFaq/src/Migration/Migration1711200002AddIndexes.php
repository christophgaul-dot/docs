<?php

declare(strict_types=1);

namespace WG\Faq\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1711200002AddIndexes extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1711200002;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE INDEX `idx.wg_faq.active_position` ON `wg_faq` (`active`, `position`);
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
