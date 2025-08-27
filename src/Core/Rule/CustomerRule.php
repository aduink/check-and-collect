<?php

declare(strict_types=1);

namespace Adu\CheckAndCollect\Core\Rule;


use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;



class CustomerRule extends Rule
{

    protected $isAdditionalinfo;

    /**
     * @var mixed
     */

    public function __construct()
    {
        parent::__construct();
        $this->isAdditionalinfo = 1;
    }


    /**
     * @return string
     */
    public function getName(): string
    {
        return 'additionalinfo';
    }

    /**
     * @param RuleScope $scope
     * @return bool
     */
    public function match(RuleScope $scope): bool
    {
        $resultSet = [
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

            //Scoreinfo
            if(!isset($customArr['adu_additional_value'])){
                return true;
            }

            // Customer ausgenommen von der Prüfung?
            $denyCustomCheck = (array_key_exists('deny_solvencycheck_user', $customArr)) ? $customArr['deny_solvencycheck_user'] : false;

            if ($denyCustomCheck) {
                return true;
            }

            $additionalinfo = $customArr['adu_additional_value'];
            $ret = false;

            switch ($this->operator) {
                case self::OPERATOR_EQ:
                    $ret = strpos($additionalinfo, $resultSet[$this->isAdditionalinfo]) !== false;
                    break;
                case self::OPERATOR_NEQ:
                    $ret = strpos($additionalinfo, $resultSet[$this->isAdditionalinfo]) === false;
                    break;
            }

        }else{
            // Noch nichts ermittelt. Keine Sperrung
            return true;
        }
        return $ret;
    }

    /**
     * @return array
     */
    public function getConstraints(): array
    {
        return [
            'isAdditionalinfo' => [
                new Type('int')
            ],
            'operator' => [
                new NotBlank(),
                new Choice([
                    self::OPERATOR_NEQ,
                    self::OPERATOR_EQ,
                ])
            ]
        ];
    }

}
