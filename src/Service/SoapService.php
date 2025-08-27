<?php
declare(strict_types=1);

namespace Adu\CheckAndCollect\Service;

use Adu\CheckAndCollect\Model\ServiceLocator;
use Shopware\Core\Framework\Routing\Event\SalesChannelContextResolvedEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Adu\CheckAndCollect\Model\SoapHandler;
use Composer\InstalledVersions;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;


/**
 *
 * @author Allgemeiner Debitoren- und Inkassodienst GmbH
 *         Klasse zum abrufen und aufbereiten der API Daten
 */
class SoapService
{

    private $login;
    private $password;
    private $test;
    private $soap;
    private $defaultCustomerScore;
    private $defaultCompanyScore;
    public $activeApi;
    public $activeLog;
    public $ipAddress;
    public $maxAge;
    public $checkCompany;
    private $salesChannelId;
    private SystemConfigService $systemConfigService;
    private EventDispatcherInterface $eventDispatcher;

    /**
     * Constructor
     *
     * @param SystemConfigService $systemConfigService
     */
    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
        // Default Scope
        $this->setScope();
    }

    /**
     * Set die Saleschannel ID des Shopscope
     * @param $salesChannelId
     * @return void
     */
    public function setScope($salesChannelId = NULL): void
    {
        $this->salesChannelId = $salesChannelId;
        $this->login = $this->systemConfigService->get('AduinCheckAndCollect.config.login', $this->salesChannelId);

        $this->password = $this->systemConfigService->get('AduinCheckAndCollect.config.password', $this->salesChannelId);
        $this->test = $this->systemConfigService->get('AduinCheckAndCollect.config.test', $this->salesChannelId);
        $this->defaultCustomerScore = $this->systemConfigService->get('AduinCheckAndCollect.config.defaultNoResult', $this->salesChannelId);
        $this->defaultCompanyScore = $this->systemConfigService->get('AduinCheckAndCollect.config.defaultCompany', $this->salesChannelId);
        $this->checkCompany = $this->systemConfigService->get('AduinCheckAndCollect.config.checkCompany', $this->salesChannelId);
        $this->soap = new SoapHandler($this->login, $this->password, $this->test);
        $this->activeApi = $this->systemConfigService->get('AduinCheckAndCollect.config.activeApi', $this->salesChannelId);
        $this->activeLog = $this->systemConfigService->get('AduinCheckAndCollect.config.activeLog', $this->salesChannelId);
        $this->ipAddress = $this->systemConfigService->get('AduinCheckAndCollect.config.ipAddress', $this->salesChannelId);
        $this->maxAge = ($this->systemConfigService->get('AduinCheckAndCollect.config.maxAge', $this->salesChannelId)) ? $this->systemConfigService->get('AduinCheckAndCollect.config.maxAge') : '10';
    }


    /**
     * Abrufe des Riskmanagement
     *
     * @param array $customer
     * @param boolean $cache
     * @return array
     */
    public function getSolvencyCheck(array $customer, $cache = true)
    {
        // Required
        $rq = ['firstname', 'lastname', 'street', 'housenumber', 'zipcode', 'city', 'phone', 'email', 'birthday', 'country'];
        foreach ($rq as $v) {
            $customer[$v] = (isset($customer[$v])) ? $customer[$v] : '';
        }

        // Max Age
        $customer['shopsetting']['maxtime'] = $this->maxAge;

        // Aus dem Cache?
        $customer['shopsetting']['cache'] = $cache;

        // Neu
        $street = $this->getStreetFromBilling($customer['street']);
        $customer['housenumber'] = $this->getStreetNumberFromBilling($customer['street']);
        $customer['street'] = $street;

        // Firma?
        $company = isset($customer['company']) && !empty($customer['company']);
        if ($company) {
            $customer['shopsetting']['company'] = true;
            $customer['firstname'] = $customer['lastname'] . ', ' . $customer['firstname'];
            $customer['lastname'] = $customer['company'];
        }

        if (InstalledVersions::isInstalled('shopware/platform')) {
            $shopwareVersion = InstalledVersions::getVersion('shopware/platform')
                . '@' . InstalledVersions::getReference('shopware/platform');
        } else {
            $shopwareVersion = InstalledVersions::getVersion('shopware/core')
                . '@' . InstalledVersions::getReference('shopware/core');
        }

        $customer['birthday'] = $customer['birthday'] instanceof \DateTimeInterface
            ? date_format($customer['birthday'], "Y-m-d")
            : '';

        $data = array(
            '4', // Bonitätsprüfung
            $customer['firstname'],
            $customer['lastname'],
            $customer['street'],
            $customer['housenumber'],
            $customer['zipcode'],
            $customer['city'],
            $customer['phone'],
            $customer['email'],
            $customer['birthday'],
            $customer['ordernumber'] . ' ' . date('d.m.Y H:i'), // Referenznummer/Aktenzeichen (string)
            false, // ist juristische Person (Boolean)
            true, // vollständige Informationen im XML Format (boolean)
            $this->test, // kein Test
            $customer['country'],
            $shopwareVersion,
            CHECKANDCOLLECTVERSION,
            serialize($customer['shopsetting']),
            $customer['customerId']
        );

        // Defaultscore - falls keine Rückgabe erfolgen kann
        $score = $this->defaultCustomerScore;

        // Abruf - nur Privatpersonen
        if (true === $company && !$this->checkCompany) {
            $score = $this->defaultCompanyScore;
            $info = 'Firmen können aktuell nicht geprüft werden.';
            $error = true;
        } else {
            // API Call
            try {
                $res = $this->soap->callClient('getConsumerCheck', $data);
                // XML Rückgabe @todo: neues Austauschformat
                if (strpos($res, "<") !== false) {
                    $xml = new \SimpleXMLElement($res);

                    if (!isset($xml->SCORE_WERT)) {
                        throw new \Exception('Score konnte nicht abgefragt werden.');
                    }

                    // Format
                    $score = intval($xml->SCORE_WERT);
                    if($score){
                        $score /= 10;
                    }else{
                        $score = $this->defaultCustomerScore;
                    }

                    // XML Ausgabe für die Ausgabe in HTML umwandeln
                    $info = (string)$xml->HINWEIS_TEXT;
                    $error = false;
                } else {
                    $info = (string)$res;
                    $error = true;
                }
            } catch (\Exception $e) {
                $info = 'Service nicht erreichbar.' . $e->getMessage() . ' ' . $res;
                $error = true;
            }

        }

        // Rückgabe Array
        return [
            'score' => floatval($score),
            'additionalInfo' => $info,
            'company' => $company,
            'error' => $error
        ];
    }


    /**
     * @return array{token:string}|null
     */
    public function getToken(): ?array
    {
        // Test
        $token = $this->soap->callClient('getToken');
        return json_decode($token, true);
    }

    /**
     * @return array|mixed|object
     */
    public function getCredits()
    {
        // Test
        $credits = $this->soap->callClient('getUserCredits');
        return json_decode($credits, true);
    }

    /**
     * @param $street
     * @return string
     */
    private function getStreetFromBilling($street)
    {
        try {
            $tmp = $this->splitAddress($street);
            if (!isset($tmp['streetName']) || empty($tmp['streetName'])) {
                $tmp['streetName'] = $street;
            }
            return $tmp['streetName'];
        } catch (\Exception $e) {
            return $street;
        }
    }

    /**
     * @param $street
     * @return string
     */
    private function getStreetNumberFromBilling($street)
    {
        try {
            $tmp = $this->splitAddress($street);
            return $tmp['houseNumber'];
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * @param string $address
     * @return array
     * @throws Exception
     */
    private function splitAddress($address)
    {
        $regex = '/\A\s*
        (?: #########################################################################
            # Option A: [<Addition to address 1>] <House number> <Street name>      #
            # [<Addition to address 2>]                                             #
            #########################################################################
            (?:(?P<A_Addition_to_address_1>.*?),\s*)? # Addition to address 1
        (?:No\.\s*)?
            (?P<A_House_number>\pN+[a-zA-Z]?(?:\s*[-\/\pP]\s*\pN+[a-zA-Z]?)*) # Street name
        \s*,?\s*
            (?P<A_Street_name>(?:[a-zA-Z]\s*|\pN\pL{2,}\s\pL)\S[^,#]*?(?<!\s)) # House number
        \s*(?:(?:[,\/]|(?=\#))\s*(?!\s*No\.)
            (?P<A_Addition_to_address_2>(?!\s).*?))? # Addition to address 2
        |   #########################################################################
            # Option B: [<Addition to address 1>] <Street name> <House number>      #
            # [<Addition to address 2>]                                             #
            #########################################################################
            (?:(?P<B_Addition_to_address_1>.*?),\s*(?=.*[,\/]))? # Addition to address 1
            (?!\s*No\.)(?P<B_Street_name>\S\s*\S(?:[^,#](?!\b\pN+\s))*?(?<!\s)) # House number
        \s*[\/,]?\s*(?:\sNo\.)?\s+
            (?P<B_House_number>\pN+\s*-?[a-zA-Z]?(?:\s*[-\/\pP]?\s*\pN+(?:\s*[\-a-zA-Z])?)*|[IVXLCDM]+(?!.*\b\pN+\b))(?<!\s) # Street name
        \s*(?:(?:[,\/]|(?=\#)|\s)\s*(?!\s*No\.)\s*
            (?P<B_Addition_to_address_2>(?!\s).*?))? # Addition to address 2
        )
        \s*\Z/x';
        $result = preg_match($regex, $address, $matches);
        try {
            if ($result === 0) {
                throw new \Exception('Address \'' . $address . '\' could not be splitted into street name and house number');
            } else {
                if ($result === false) {
                    throw new \Exception('Error occured while trying to split address \'' . $address . '\'');
                }
            }
        } catch (\Exception $e) {
            $streetName = '';
            $houseNumber = '';
            $all = explode('.', $address);
            if (count($all) > 1) {
                $houseNumber = $all[count($all) - 1];
                unset($all[count($all) - 1]);
                $streetName = implode('.', $all) . '.';
            }

            return array(
                'additionToAddress1' => '',
                'streetName' => $streetName,
                'houseNumber' => $houseNumber,
                'additionToAddress2' => ''
            );
        }


        $res = [];
        if (!empty($matches['A_Street_name'])) {
            isset($matches['A_Addition_to_address_1']) ? $res['additionToAddress1'] = $matches['A_Addition_to_address_1'] : NULL;
            isset($matches['A_Street_name']) ? $res['streetName'] = $matches['A_Street_name'] : NULL;
            isset($matches['A_House_number']) ? $res['houseNumber'] = $matches['A_House_number'] : NULL;
            isset($matches['A_Addition_to_address_2']) ? $res['additionToAddress2'] = $matches['A_Addition_to_address_2'] : NULL;
        } else {
            isset($matches['B_Addition_to_address_1']) ? $res['additionToAddress1'] = $matches['B_Addition_to_address_1'] : NULL;
            isset($matches['B_Street_name']) ? $res['streetName'] = $matches['B_Street_name'] : NULL;
            isset($matches['B_House_number']) ? $res['houseNumber'] = $matches['B_House_number'] : NULL;
            isset($matches['B_Addition_to_address_2']) ? $res['additionToAddress2'] = $matches['B_Addition_to_address_2'] : NULL;

        }

        return $res;
    }
}
