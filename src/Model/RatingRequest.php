<?php

namespace Adu\CheckAndCollect\Model;

use Adu\CheckAndCollect\Exception\B2bRequestNotActivated;
use Adu\CheckAndCollect\Exception\CustomerCannotBeScoredException;
use Adu\CheckAndCollect\Service\AduConfig;
use Composer\InstalledVersions;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;

class RatingRequest
{
    public string $street;
    public string $house;
    public array $extraAdress;
    public readonly bool $isCompany;
    public string $product;
    public ?float $amount = null;
    public ?string $birthDay;
    public string $firstName;
    public string $name;
    private bool $cache = false;
    private string $countryIso;
    private static array $instances = [];

    /**
     * @throws CustomerCannotBeScoredException
     */
    public function __construct(public readonly CustomerAddressEntity $address, public readonly CustomerEntity $customer)
    {
        $this->isCompany = !empty($address->getCompany());
        $this->parseStreet();
        $this->getProduct();
        $this->getFirstName();
        $this->getName();
        $this->setBirthday();
    }
    public function setCache(bool $cache): self
    {
        $this->cache = $cache;
        return $this;
    }
    public function setAmount(float $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    /**
     * @throws CustomerCannotBeScoredException
     * @throws B2bRequestNotActivated
     */
    public function validate(AduConfig $config): void
    {
        $len = $this->countryIso === "DE" ? 5 : 4;
        $zip = $this->address->getZipcode() ?? '';
        if(strlen($zip) !== $len){
            throw new CustomerCannotBeScoredException("Invalid Zip for $this->countryIso ($zip)");
        }
        if ($this->isBusiness() && !$config->checkCompany()) {
            throw new B2bRequestNotActivated;
        }
        if(empty($this->street)){
            throw new CustomerCannotBeScoredException("Straße ist leer");
        }
        if(empty($this->house)){
            throw new CustomerCannotBeScoredException("Hausnummer ist leer");
        }
    }

    public function getReference(): string
    {
        return $this->customer->getId() . " " . date("d.m.Y H:i");
    }

    public function getCustomerNumber(): string
    {
        return $this->customer->getCustomerNumber();
    }
    public function getCustomerEntityId(): string
    {
        return $this->customer->getId();
    }

    /**
     * @throws CustomerCannotBeScoredException
     */
    public static function getAddressFromCustomer(CustomerEntity $customer): CustomerAddressEntity {
        $billing = $customer->getActiveBillingAddress() ?? $customer->getDefaultBillingAddress();
        $shipping = $customer->getActiveShippingAddress() ?? $customer->getDefaultShippingAddress();
        if (!($shipping && $billing)) {
            if ($shipping) {
                return $shipping;
            }
            if ($billing) {
                return $billing;
            }
            $any = $customer->getAddresses()->first();
            if ($any) {
                return $any;
            }
            throw new CustomerCannotBeScoredException("Keine Adressen gefunden");
        }
        if ($shipping->getId() === $billing->getId()) {
            return $shipping;
        }
        if ($shipping->getFirstName() === $billing->getFirstName() && $shipping->getLastName() === $billing->getLastName()) {
            return $billing; // Der Kunde hat verschiedene Adressen mit dem gleichen Namen. Dann ist wahrscheinlich die Rechnungsadresse seine Residenz
        }
        return $shipping; // Um Fraud zu verhindern wird bei 2 verschiedenen Adressen zu verhindert wird dann auf die Lieferadresse zugegriffen
    }

    /**
     * @throws CustomerCannotBeScoredException
     */
    public static function fromCustomer(CustomerEntity $customer): RatingRequest
    {
        if(!isset(self::$instances[$customer->getId()])){
            $address = self::getAddressFromCustomer($customer);
            self::$instances[$customer->getId()] =  new self($address, $customer);
        }
        return self::$instances[$customer->getId()];
    }

    /**
     * @throws CustomerCannotBeScoredException
     */
    private function parseStreet(): void
    {
        $address = $this->address->getStreet();
        $regex = '/\A\s*
        (?: #########################################################################
            # Option A: [<Addition to address 1>] <House number> <Street name>      #
            # [<Addition to address 2>]                                             #
            #########################################################################
            (?:(?P<a_extra_1>.*?),\s*)? # Addition to address 1
        (?:No\.\s*)?
            (?P<a_house>\pN+[a-zA-Z]?(?:\s*[-\/\pP]\s*\pN+[a-zA-Z]?)*) # House number
        \s*,?\s*
            (?P<a_street>(?:[a-zA-Z]\s*|\pN\pL{2,}\s\pL)\S[^,#]*?(?<!\s)) # Street
        \s*(?:(?:[,\/]|(?=\#))\s*(?!\s*No\.)
            (?P<a_extra_2>(?!\s).*?))? # Addition to address 2
        |   #########################################################################
            # Option B: [<Addition to address 1>] <Street name> <House number>      #
            # [<Addition to address 2>]                                             #
            #########################################################################
            (?:(?P<b_extra_1>.*?),\s*(?=.*[,\/]))? # Addition to address 1
            (?!\s*No\.)(?P<b_street>\S\s*\S(?:[^,#](?!\b\pN+\s))*?(?<!\s)) # Street
        \s*[\/,]?\s*(?:\sNo\.)?\s*
            (?P<b_house>\pN+\s*-?[a-zA-Z]?(?:\s*[-\/\pP]?\s*\pN+(?:\s*[\-a-zA-Z])?)*|[IVXLCDM]+(?!.*\b\pN+\b))(?<!\s) # House Number
        \s*(?:(?:[,\/]|(?=\#)|\s)\s*(?!\s*No\.)\s*
            (?P<b_extra_2>(?!\s).*?))? # Addition to address 2
        )
        \s*\Z/x';
        $result = preg_match($regex, $address, $m);
        if (!$result) {
            throw new CustomerCannotBeScoredException("Adresse konnte nicht als Straße und Hausnummer interpretiert werden");
        }
        $return = array_map(
            fn (string $prefix) => [
                'street' => $m["{$prefix}_street"] ?? null,
                'house' => $m["{$prefix}_house"] ?? null,
                'extra' => [
                    $m["{$prefix}_extra_1"] ?? null,
                    $m["{$prefix}_extra_2"] ?? null,
                ]
            ],
            ['a', 'b']
        );
        foreach($return as $arr){
            if(empty($arr['street']) || empty($arr['house'])){
                continue;
            }
            $this->street = $arr['street'];
            $this->house = $arr['house'];
            $this->extraAdress = array_values(array_filter($arr['extra']));
            return;
        }
        throw new CustomerCannotBeScoredException("Adresse konnte nicht als Straße und Hausnummer interpretiert werden");
    }

    /**
     * @throws CustomerCannotBeScoredException
     */
    private function getProduct(): void
    {
        $this->countryIso = $this->address->getCountry()?->getIso() ?? "DE";
        if ($this->isCompany) {
            if ($this->countryIso !== 'DE') {
                throw new CustomerCannotBeScoredException("Firmenprüfung nur in Deutschland möglich");
            }
            $this->product = 'checkB2b';
            return;
        }
        if (!in_array($this->countryIso, ['DE', 'CH', 'AT'])) {
            throw new CustomerCannotBeScoredException("Boniprüfung ist nur in der DACH-Region möglich");
        }
        $product = 'ConCheck';
        if (in_array($this->countryIso, ['CH', 'AT'])) {
            $product .= $this->countryIso;
        }
        $this->product = $product;
    }

    public function isBusiness(): bool
    {
        return $this->isCompany;
    }

    private function getFirstName(): void
    {
        $this->firstName = $this->isCompany ? $this->address->getLastName() . ', ' . $this->address->getFirstName() : $this->address->getFirstName();
    }

    private function getName(): void
    {
        $this->name = $this->isCompany ? $this->address->getCompany() : $this->address->getLastName();
    }

    /**
     * @throws CustomerCannotBeScoredException
     */
    private function setBirthday(): void
    {
        $birthday = $this->address->getCustomer()?->getBirthday();
        if (!$birthday instanceof \DateTimeInterface) {
            $this->birthDay = null;
        }
        if ($birthday instanceof \DateTimeInterface) {
            $now = new \DateTime();
            if ($now->diff($birthday)->y < 18) {
                throw new CustomerCannotBeScoredException("Prüfung von Minderjährigen Personen nicht möglich");
            }
            $this->birthDay = $birthday->format("Y-m-d");
        }
    }

    public function toAdu(AduConfig $config): array {
        $data = [
            'reasonId' => 4,
            'reference' => $this->customer->getId() . " " . date("d.m.Y H:i"),
            'firstName' => $this->firstName,
            'name' => $this->name,
            'street' => $this->street,
            'house' => $this->house,
            'zip' => $this->address->getZipcode(),
            'city' => $this->address->getCity(),
            'test' => $config->test(),
            'shopSetting' => [
                'shop' => $this->getShopwareVersion(),
                'maxTime' => $config->maxAge(),
                'customerId' => $this->customer->getCustomerNumber(),
                'customerEntityId' => $this->customer->getId(),
                'amount' => $this->amount,
                'checkAndCollect' => CHECKANDCOLLECTVERSION,
                'salutation' => $this->address->getSalutation()?->getLetterName(),
                'cache' => $this->cache,
                'company' => $this->isCompany,
            ],
        ];
        if($this->birthDay){
            $data['dateOfBirth'] = $this->birthDay;
        }
        return $data;
    }
    private function getShopwareVersion(): string
    {
        $pl = 'shopware/platform';
        $fmt = fn(string $key) => InstalledVersions::getVersion($key) . '@' . InstalledVersions::getReference($key);
        return $fmt(InstalledVersions::isInstalled($pl) ? $pl : 'shopware/core');
    }
}
