<?php

declare(strict_types=1);

namespace Adu\CheckAndCollect\Core\Rule;

use Adu\CheckAndCollect\Service\RuleTrait;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;


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
        // Service Locator mit Abhängigkeiten
        $this->loadLocator();

        if ($this->shoudlSkipCheck($scope)) {
            return true;
        }

        $info = $this->getCachedInfo($scope);
        if($info !== null){
            return $this->compare($info);
        }

        if($this->cantCheck($scope)){
            return true;
        }

        try{
            /** @var string $additionalinfo */
            $additionalinfo = $this->getNewScore($scope->getSalesChannelContext())['additionalInfo'];
            return $this->compare($additionalinfo);
        }catch (\Throwable $e){
            $this->logger("ERROR: ".$e->getMessage(). "\n\n", true);
            return true;
        }
    }

    private function getCachedInfo(RuleScope $scope): ?string {
        return $_SESSION['_sf2_attributes']['adu_score_value']['additionalInfo'] ??
            $scope->getSalesChannelContext()->getCustomer()?->getCustomFields()['adu_additional_value'] ??
            null;
    }
    private function compare(string $info): bool {
        $ret = str_contains($info, self::RULESET[$this->isAdditionalinfo]);
        $return =  $this->operator === self::OPERATOR_EQ ? $ret : !$ret;
        $this->logger(self::RULESET[$this->isAdditionalinfo]." in $info?". ($ret ? "Ja" : "Nein"));
        return $return;
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
