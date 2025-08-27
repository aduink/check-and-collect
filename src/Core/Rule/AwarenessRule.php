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
class AwarenessRule extends Rule
{
    use RuleTrait;

    protected int $isCode;
    private const RULESET = [
        1 => ['N1', 'A1'],
        2 => ['N2', 'A2', 'NW', 'A5'],
        3 => ['N0', 'A0'],
    ];
    /**
     * BEKANNT
     * N1: Person/Anschrift bekannt
     * A1: Firma Anschrift person Haushalt bekannt
     *
     * UNSICHER
     * N2: Person/Anschrift abweichend
     * A2: Firma/Anschrift abweichend
     * NW: Person unbekannt anschrift bekannt
     * A5: Mehrere Personen bekannt
     *
     * UNBEKANNT
     * N0: Person/Anschrift unbekannt
     * A0: Firma/Pers/Haushalt unbekannt
     *
     */

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'awareness';
    }

    private function compare($code): bool
    {
        $ret = in_array($code, self::RULESET[$this->isCode]);
        $return = $this->operator === self::OPERATOR_EQ ? $ret : !$ret;
        $this->logger->log("Prüfung in AwarenessRule: ", context: [
            'code' => $code,
            'operator' => $this->operator,
            'einstellungs_codes' => self::RULESET[$this->isCode],
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
            'isCode' => [new Type('int')],
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
        return AduConfig::AWARENESS;
    }

    private function getValueType(): string
    {
        return 'string';
    }

    private function getDefaultValue(bool $b2b): string
    {
        return $b2b ? 'A0' : 'N0';
    }

    private function getValueFromScoring(Scoring $scoring): ?string
    {
        return $scoring->getCode();
    }
}
