<?php declare(strict_types=1);

namespace VvWhatsAppAssistant\Controller;

use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use VvWhatsAppAssistant\Service\BotReplyService;
use VvWhatsAppAssistant\Service\MessageLogService;
use VvWhatsAppAssistant\Service\WhatsAppMessageSender;

class WhatsAppWebhookController extends AbstractController
{
    private const CONFIG_PREFIX = 'VvWhatsAppAssistant.config.';

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly BotReplyService $botReplyService,
        private readonly WhatsAppMessageSender $messageSender,
        private readonly MessageLogService $messageLogService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function verify(Request $request): Response
    {
        $configuredToken = (string) ($this->systemConfigService->get(self::CONFIG_PREFIX . 'webhookVerifyToken') ?? '');
        $mode = (string) $request->query->get('hub_mode', $request->query->get('hub.mode', ''));
        $verifyToken = (string) $request->query->get('hub_verify_token', $request->query->get('hub.verify_token', ''));
        $challenge = (string) $request->query->get('hub_challenge', $request->query->get('hub.challenge', ''));

        if ($configuredToken !== '' && $challenge !== '' && ($mode === '' || $mode === 'subscribe') && hash_equals($configuredToken, $verifyToken)) {
            return new Response($challenge, Response::HTTP_OK, ['Content-Type' => 'text/plain']);
        }

        return new Response('Forbidden', Response::HTTP_FORBIDDEN);
    }

    public function receive(Request $request): JsonResponse
    {
        $payload = $this->decodePayload($request);

        if ($payload === null) {
            $this->logger->warning('WhatsApp webhook received invalid JSON payload.');

            return new JsonResponse(['status' => 'ignored'], Response::HTTP_OK);
        }

        $messages = $this->extractTextMessages($payload);

        foreach ($messages as $message) {
            if ($message['messageId'] !== '' && $this->messageLogService->hasMessageId($message['messageId'])) {
                continue;
            }

            $this->messageLogService->logIncoming(
                $message['messageId'],
                $message['from'],
                $message['text'],
                $payload,
                'received'
            );

            $reply = $this->botReplyService->getReply($message['text']);
            $sendResult = $this->messageSender->sendTextMessage($message['from'], $reply);

            $this->messageLogService->logOutgoing(
                $message['from'],
                $reply,
                $sendResult['status'],
                $sendResult['error'] ?? null
            );
        }

        return new JsonResponse(['status' => 'ok'], Response::HTTP_OK);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodePayload(Request $request): ?array
    {
        $content = $request->getContent();

        if ($content === '') {
            return [];
        }

        try {
            $payload = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return \is_array($payload) ? $payload : null;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<array{messageId: string, from: string, text: string, timestamp: string}>
     */
    private function extractTextMessages(array $payload): array
    {
        $result = [];
        $entries = \is_array($payload['entry'] ?? null) ? $payload['entry'] : [];

        foreach ($entries as $entry) {
            $changes = \is_array($entry['changes'] ?? null) ? $entry['changes'] : [];

            foreach ($changes as $change) {
                $value = \is_array($change['value'] ?? null) ? $change['value'] : [];
                $messages = \is_array($value['messages'] ?? null) ? $value['messages'] : [];

                foreach ($messages as $message) {
                    if (!\is_array($message) || ($message['type'] ?? null) !== 'text') {
                        continue;
                    }

                    $from = (string) ($message['from'] ?? '');
                    $messageId = (string) ($message['id'] ?? '');
                    $text = (string) ($message['text']['body'] ?? '');

                    if ($from === '' || $text === '') {
                        continue;
                    }

                    $result[] = [
                        'messageId' => $messageId,
                        'from' => $from,
                        'text' => $text,
                        'timestamp' => (string) ($message['timestamp'] ?? ''),
                    ];
                }
            }
        }

        return $result;
    }
}
