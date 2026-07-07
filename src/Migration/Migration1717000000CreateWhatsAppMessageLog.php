<?php declare(strict_types=1);

namespace VvWhatsAppAssistant\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1717000000CreateWhatsAppMessageLog extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1717000000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<SQL
CREATE TABLE IF NOT EXISTS `vv_whatsapp_message_log` (
    `id` BINARY(16) NOT NULL,
    `message_id` VARCHAR(255) NULL,
    `direction` VARCHAR(20) NOT NULL,
    `customer_phone` VARCHAR(64) NULL,
    `message_text` LONGTEXT NULL,
    `payload_json` JSON NULL,
    `status` VARCHAR(64) NULL,
    `created_at` DATETIME(3) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.vv_whatsapp_message_log.message_id` (`message_id`),
    KEY `idx.vv_whatsapp_message_log.customer_phone` (`customer_phone`),
    KEY `idx.vv_whatsapp_message_log.created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
