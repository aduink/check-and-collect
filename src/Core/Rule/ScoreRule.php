<?php

declare(strict_types=1);

namespace Adu\CheckAndCollect\Core\Rule;


use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;



class ScoreRule extends Rule
{

    /**
     * @var float
     */
    protected $isScoreValue;

    /**
     * @var mixed
     */
    private $soap;

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
     */
    public function match(RuleScope $scope): bool
    {
        // Vollständige Daten werden benötigt.
        $customer = $scope->getSalesChannelContext()->getCustomer();

        // Aktiv, Checkout und Kunde vorhanden?
        if (!$scope instanceof CheckoutRuleScope || !$customer) {
            return true;
        }

        // Customer Attribute
        $customArr = $customer->getCustomFields();

        //  Kunde vorhanden schon geprüft?
        if (NULL != $customArr || (isset($_SESSION) && array_key_exists('score', $_SESSION['_sf2_attributes']))) {

            // Session zugriff bei der ersten Verarbeitung
            if(NULL == $customArr){
                $customArr['adu_score_value'] = $_SESSION['_sf2_attributes']['score']['score'];
                $customArr['adu_additional_value'] = $_SESSION['_sf2_attributes']['score']['additionalInfo'];
                $customArr['deny_solvencycheck_user'] = NULL;
            }

            //Score
            $score = $customArr['adu_score_value'];

            // Customer ausgenommen von der Prüfung?
            $denyCustomCheck = (array_key_exists('deny_solvencycheck_user', $customArr)) ? $customArr['deny_solvencycheck_user'] : false;

            if ($denyCustomCheck) {
                return true;
            }
        }else{
            // Noch nichts ermittelt. Keine Sperrung
            return true;
        }

        switch ($this->operator) {
            case self::OPERATOR_EQ:
                $ret = $this->isScoreValue === $score;
                break;
            case self::OPERATOR_NEQ:
                $ret = $this->isScoreValue !== $score;
                break;
            case self::OPERATOR_LT:
                $ret = $this->isScoreValue > $score;
                break;
            case self::OPERATOR_GT:
                $ret = $this->isScoreValue < $score;
                break;
            case self::OPERATOR_LTE:
                $ret = $this->isScoreValue >= $score;
                break;
            case self::OPERATOR_GTE:
                $ret = $this->isScoreValue <= $score;
                break;
            default:
                throw new UnsupportedOperatorException((string)$this->isScoreValue, self::class);
        }

        // Treffer? Dann false
        return $ret;
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
