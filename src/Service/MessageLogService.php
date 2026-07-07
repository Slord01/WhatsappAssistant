<?php declare(strict_types=1);

namespace VvWhatsAppAssistant\Service;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Uuid\Uuid;

class MessageLogService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger
    ) {
    }

    public function hasMessageId(string $messageId): bool
    {
        if ($messageId === '') {
            return false;
        }

        try {
            $existing = $this->connection->fetchOne(
                'SELECT message_id FROM vv_whatsapp_message_log WHERE message_id = :messageId LIMIT 1',
                ['messageId' => $messageId]
            );
        } catch (\Throwable $exception) {
            $this->logger->warning('Unable to check WhatsApp duplicate message ID.', ['error' => $exception->getMessage()]);

            return false;
        }

        return \is_string($existing) && $existing !== '';
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function logIncoming(string $messageId, string $customerPhone, string $messageText, array $payload, string $status): void
    {
        $this->insertLog($messageId, 'incoming', $customerPhone, $messageText, json_encode($payload, \JSON_THROW_ON_ERROR), $status);
    }

    public function logOutgoing(string $customerPhone, string $messageText, string $status, ?string $error = null): void
    {
        $payload = $error !== null ? json_encode(['error' => $error], \JSON_THROW_ON_ERROR) : null;

        $this->insertLog(null, 'outgoing', $customerPhone, $messageText, $payload, $status);
    }

    private function insertLog(?string $messageId, string $direction, string $customerPhone, string $messageText, ?string $payloadJson, string $status): void
    {
        try {
            $this->connection->insert('vv_whatsapp_message_log', [
                'id' => Uuid::randomBytes(),
                'message_id' => $messageId !== '' ? $messageId : null,
                'direction' => $direction,
                'customer_phone' => $customerPhone,
                'message_text' => $messageText,
                'payload_json' => $payloadJson,
                'status' => $status,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
            ]);
        } catch (\Throwable $exception) {
            $this->logger->warning('Unable to write WhatsApp message log.', ['error' => $exception->getMessage()]);
        }
    }
}
