<?php declare(strict_types=1);

namespace VvWhatsAppAssistant\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class BotReplyService
{
    private const CONFIG_PREFIX = 'VvWhatsAppAssistant.config.';

    public function __construct(private readonly SystemConfigService $systemConfigService)
    {
    }

    public function getReply(string $incomingText): string
    {
        $text = $this->normalize($incomingText);

        if ($text === '' || $this->isStartRequest($text)) {
            return $this->getConfiguredMessage('defaultStartMessage', self::defaultStartMessage());
        }

        if ($text === '1' || $this->containsAny($text, ['bestellung', 'versand', 'lieferung', 'tracking'])) {
            return "Gerne helfen wir Ihnen bei Ihrer Bestellung oder Lieferung.\n\nBitte senden Sie uns Ihre Bestellnummer und die E-Mail-Adresse, mit der die Bestellung aufgegeben wurde.\n\nBeispiel:\nBestellnummer: 12345\nE-Mail: name@example.de";
        }

        if ($text === '2' || $this->containsAny($text, ['zahlung', 'rechnung', 'paypal', 'kreditkarte'])) {
            return "Gerne helfen wir Ihnen bei Fragen zur Zahlung.\n\nBitte senden Sie uns Ihre Bestellnummer und eine kurze Beschreibung des Problems.";
        }

        if ($text === '3' || $this->containsAny($text, ['ruckgabe', 'reklamation', 'defekt', 'widerruf'])) {
            return "Gerne helfen wir Ihnen bei Rückgaben oder Reklamationen.\n\nBitte senden Sie uns Ihre Bestellnummer und beschreiben Sie kurz, worum es geht. Falls ein Produkt beschädigt oder defekt ist, können Sie uns auch ein Foto senden.";
        }

        if ($text === '4' || $this->containsAny($text, ['geschmack', 'liquid', 'aroma', 'fresh', 'suss', 'fruchtig', 'tobacco', 'tabak'])) {
            return "Gerne helfen wir Ihnen bei der Geschmacksorientierung.\n\nWelche Richtung bevorzugen Sie?\n\n1. Frisch / Ice / Menthol\n2. Fruchtig\n3. Süß / Dessert\n4. Tabak\n5. Ich bin mir nicht sicher\n\nHinweis: Nikotinhaltige Produkte sind ausschließlich für Personen ab 18 Jahren bestimmt.";
        }

        if ($text === '5' || $this->containsAny($text, ['pod', 'gerat', 'coil', 'kompatibel'])) {
            return "Gerne helfen wir Ihnen bei der Kompatibilität.\n\nBitte schreiben Sie uns den Namen Ihres Geräts oder Pods. Wenn möglich, senden Sie auch ein Foto der Verpackung oder des Produkts.";
        }

        if ($text === '6' || $this->containsAny($text, ['mitarbeiter', 'mensch', 'support'])) {
            return $this->getConfiguredMessage('humanHandoverMessage', 'Ich leite Ihre Anfrage an einen Mitarbeiter weiter. Unser Support-Team meldet sich schnellstmöglich bei Ihnen.');
        }

        return "Vielen Dank für Ihre Nachricht.\n\nIch kann Ihnen bei Bestellung, Versand, Zahlung, Rückgabe, Geschmacksberatung oder Geräte-Kompatibilität helfen.\n\nBitte schreiben Sie kurz Ihr Anliegen oder wählen Sie eine Zahl:\n\n1. Bestellung & Versand\n2. Zahlung\n3. Rückgabe / Reklamation\n4. Produkt- und Geschmacksberatung\n5. Pod- und Geräte-Kompatibilität\n6. Mit einem Mitarbeiter sprechen";
    }

    private function getConfiguredMessage(string $key, string $fallback): string
    {
        $value = $this->systemConfigService->get(self::CONFIG_PREFIX . $key);

        return \is_string($value) && trim($value) !== '' ? $value : $fallback;
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');

        return strtr($text, [
            'ä' => 'a',
            'ö' => 'o',
            'ü' => 'u',
            'ß' => 'ss',
        ]);
    }

    /**
     * @param list<string> $needles
     */
    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function isStartRequest(string $text): bool
    {
        return $this->containsAny($text, ['start', 'menu', 'menue', 'hallo', 'hi', 'hilfe']);
    }

    private static function defaultStartMessage(): string
    {
        return "Hallo und willkommen beim Vampire Vape Support.\n\nIch kann Ihnen bei folgenden Themen helfen:\n\n1. Bestellung & Versand\n2. Zahlung\n3. Rückgabe / Reklamation\n4. Produkt- und Geschmacksberatung\n5. Pod- und Geräte-Kompatibilität\n6. Mit einem Mitarbeiter sprechen\n\nBitte schreiben Sie einfach die passende Zahl oder kurz Ihr Anliegen.\n\nHinweis: Nikotinhaltige Produkte sind ausschließlich für Personen ab 18 Jahren bestimmt.";
    }
}
