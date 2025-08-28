<?php

declare(strict_types=1);

namespace Adu\CheckAndCollect\Core\Rule;

use Adu\CheckAndCollect\Model\Scoring;
use Adu\CheckAndCollect\Service\AduConfig;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;


#[AsTaggedItem('shopware.rule')]
class ScoreRule extends Rule
{
    use RuleTrait;

    protected float $isScoreValue;

    /*
    public function __construct()
    {
        parent::__construct();
        // $this->isScoreValue = 2.7;
    }
    */

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'score';
    }

    private function compare($score): bool
    {
        $return = match ($this->operator) {
            self::OPERATOR_EQ => $this->isScoreValue === $score,
            self::OPERATOR_NEQ => $this->isScoreValue !== $score,
            self::OPERATOR_LT => $this->isScoreValue > $score,
            self::OPERATOR_GT => $this->isScoreValue < $score,
            self::OPERATOR_LTE => $this->isScoreValue >= $score,
            self::OPERATOR_GTE => $this->isScoreValue <= $score,
            default => throw new UnsupportedOperatorException("Operator $this->operator not supported.", self::class)
        };
        $this->logger->debug("Prüfung in ScoreRule: ", context: [
            'score' => $score,
            'operator' => $this->operator,
            'einstellungs_score' => $this->isScoreValue,
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
            'isScoreValue' => [
                new Type('numeric')
            ],
            'operator' => [
                new NotBlank(),
                new Choice([
                    self::OPERATOR_NEQ,
                    self::OPERATOR_GTE,
                    self::OPERATOR_LTE,
                    self::OPERATOR_EQ,
                    self::OPERATOR_GT,
                    self::OPERATOR_LT
                ])
            ]
        ];
    }

    private function getCacheKey(): string
    {
        return AduConfig::SCORE;
    }

    private function getValueType(): string
    {
        return 'double';
    }

    private function getDefaultValue(bool $b2b): float
    {
        return $b2b ? $this->config->defaultCompanyScore() : $this->config->defaultCustomerScore();
    }

    private function getValueFromScoring(Scoring $scoring): ?float
    {
        return $scoring->getScore();
    }
}
