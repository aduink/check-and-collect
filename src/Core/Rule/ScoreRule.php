<?php

declare(strict_types=1);

namespace Adu\CheckAndCollect\Core\Rule;

use Adu\CheckAndCollect\Service\RuleTrait;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;


class ScoreRule extends Rule
{

    use RuleTrait;

    protected float $isScoreValue;

    public function __construct()
    {
        parent::__construct();
        $this->isScoreValue = 2.7;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'score';
    }

    /**
     * @param RuleScope $scope
     * @return bool
     * @throws \Exception
     */
    public function match(RuleScope $scope): bool
    {
        // Service Locator mit Abhängigkeiten
        $this->loadLocator();

        if ($this->shoudlSkipCheck($scope)) {
            return true;
        }
        $score = $this->getCachedScore($scope);
        if($score !== null){
            return $this->compare($score);
        }
        if($this->cantCheck($scope)){
            return true;
        }

        try{
            /** @var float $score */
            $score = $this->getNewScore($scope->getSalesChannelContext())['score'];
            return $this->compare($score);
        }catch (\Throwable $e){
            $this->logger("ERROR: ".$e->getMessage(). "\n\n", true);
            return true;
        }
    }
    private function getCachedScore(RuleScope $scope): ?float {
        $score = $_SESSION['_sf2_attributes']['adu_score_value']['score'] ?? // Score aus session?
            $scope->getSalesChannelContext()->getCustomer()?->getCustomFields()['adu_score_value'] ??
            null;
        if(!$score){
            return null;
        }
        return (float)$score;
    }

    private function compare($score): bool{
        $return = match ($this->operator) {
            self::OPERATOR_EQ => $this->isScoreValue === $score,
            self::OPERATOR_NEQ => $this->isScoreValue !== $score,
            self::OPERATOR_LT => $this->isScoreValue > $score,
            self::OPERATOR_GT => $this->isScoreValue < $score,
            self::OPERATOR_LTE => $this->isScoreValue >= $score,
            self::OPERATOR_GTE => $this->isScoreValue <= $score,
            default => throw new UnsupportedOperatorException("Operator $this->operator not supported.", self::class)
        };
        $this->logger($score . " " . $this->operator . " " . $this->isScoreValue . "? ". ($return ? "Ja" : "Nein"));
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
}
