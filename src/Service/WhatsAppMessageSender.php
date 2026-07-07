<?php declare(strict_types=1);

namespace VvWhatsAppAssistant\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WhatsAppMessageSender
{
    private const CONFIG_PREFIX = 'VvWhatsAppAssistant.config.';
    private const GRAPH_API_VERSION = 'v20.0';

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return array{status: string, error?: string}
     */
    public function sendTextMessage(string $to, string $message): array
    {
        $enabled = (bool) $this->systemConfigService->get(self::CONFIG_PREFIX . 'enabled');
        $phoneNumberId = (string) ($this->systemConfigService->get(self::CONFIG_PREFIX . 'whatsappPhoneNumberId') ?? '');
        $accessToken = (string) ($this->systemConfigService->get(self::CONFIG_PREFIX . 'whatsappAccessToken') ?? '');

        if (!$enabled || $phoneNumberId === '' || $accessToken === '') {
            $this->logger->info('WhatsApp message sending skipped because integration is disabled or credentials are incomplete.');

            return ['status' => 'skipped'];
        }

        $url = sprintf('https://graph.facebook.com/%s/%s/messages', self::GRAPH_API_VERSION, rawurlencode($phoneNumberId));

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 5,
                'max_duration' => 8,
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'text',
                    'text' => [
                        'body' => $message,
                    ],
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                return ['status' => 'sent'];
            }

            $this->logger->warning('WhatsApp message sending failed.', [
                'statusCode' => $statusCode,
                'to' => $to,
            ]);

            return ['status' => 'failed', 'error' => 'HTTP ' . $statusCode];
        } catch (TransportExceptionInterface $exception) {
            $this->logger->warning('WhatsApp message sending failed due to transport error.', [
                'to' => $to,
                'error' => $exception->getMessage(),
            ]);

            return ['status' => 'failed', 'error' => 'transport'];
        } catch (\Throwable $exception) {
            $this->logger->warning('WhatsApp message sending failed.', [
                'to' => $to,
                'error' => $exception->getMessage(),
            ]);

            return ['status' => 'failed', 'error' => 'runtime'];
        }
    }
}
