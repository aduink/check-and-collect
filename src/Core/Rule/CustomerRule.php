<?php

declare(strict_types=1);

namespace Adu\CheckAndCollect\Core\Rule;

use Adu\CheckAndCollect\Model\Scoring;
use Adu\CheckAndCollect\Service\AduConfig;
use Shopware\Core\Framework\Rule\Rule;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;


#[AsTaggedItem('shopware.rule')]
class CustomerRule extends Rule
{
    use RuleTrait;

    protected int $isAdditionalinfo;

    private const RULESET = [
        1 => 'Person/Anschrift unbekannt;',             // ConCheck - Achtung, in Doku ist das Feld nur Person unbekannt, kommt aber anders zurück
        2 => 'Person unbekannt/Anschrift bekannt',      // ConCheck + B2B
        3 => 'Person/Anschrift bekannt',                // ConCheck + B2B
        4 => 'Person/Anschrift abweichend',             // ConCheck + B2B
        5 => 'Mehrere Personen bekannt',                // entfällt - nur zur Kompatibilität beibehalten
        6 => 'Firma und Anschrift bekannt',             // B2B
        7 => 'Person/Haushalt/Anschrift bekannt',       // ConCheck AT CH
        8 => 'Person/Haushalt unbekannt',               // ConCheck AT CH
        9 => 'Firma/Person unbekannt',                  // B2B
        10 => 'Firma/Anschrift abweichend',             // B2B
        11 => 'Mehrere Firmen bekannt',                 // B2B
    ];


    /*
    public function __construct()
    {
        parent::__construct();
        $this->isAdditionalinfo = 1;
    }
    */


    /**
     * @return string
     */
    public function getName(): string
    {
        return 'additionalinfo';
    }

    private function compare($text): bool
    {
        $ret = $text == self::RULESET[$this->isAdditionalinfo];
        $return = $this->operator === self::OPERATOR_EQ ? $ret : !$ret;
        $this->logger->log("Prüfung in CustomerRule: ", context: [
            'text' => $text,
            'operator' => $this->operator,
            'einstellungs_text' => self::RULESET[$this->isAdditionalinfo],
            'ergebnis' => $return
        ]);
        return $return;
    }

    /**
     * @return array
     */
    public function getConstraints(): array
    {
        return [
            'isAdditionalinfo' => [new Type('int')],
            'operator' => [
                new NotBlank(),
                new Choice([
                    self::OPERATOR_NEQ,
                    self::OPERATOR_EQ,
                ])
            ]
        ];
    }

    private function getCacheKey(): string
    {
        return AduConfig::ADDITIONAL;
    }

    private function getValueType(): string
    {
        return 'string';
    }

    private function getDefaultValue(bool $b2b): string
    {
        return $b2b ? 'Firma/Person unbekannt' : 'Person/Anschrift unbekannt;';
    }

    private function getValueFromScoring(Scoring $scoring): ?string
    {
        return $scoring->getInfo();
    }
}
