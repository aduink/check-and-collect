<?php
declare(strict_types=1);

namespace Adu\CheckAndCollect\Model;

class SoapHandler
{
    public $client;
    private $clientUrl = 'https://auskunft.adu-inkasso.de/Webservice/adiservice_short.wsdl';
    private $login;
    private $password;
    private $testmode;

    /**
     * Methode zum initalisieren des SOAP Handler
     * @param $login
     * @param string $password
     * @param bool $testmode
     */
    public function __construct($login, $password, $testmode = false)
    {
        ini_set("soap.wsdl_cache_enabled", '1');
        // Verfiy Peer entfernen falls der Server dies nicht beherrscht
        $opts = array(
            'http' => array(
                'user_agent' => 'PHPSoapClient'
            )
        );
        $context = stream_context_create($opts);
        $options = array(
            'stream_context' => $context,
            'soap_version' => SOAP_1_1,
            'trace' => 1
        );

        $this->login = $login;
        $this->password = $password;
        $this->testmode = $testmode;
        try {
            $this->client = new \SoapClient($this->clientUrl, $options);
        } catch (\SoapFault) {
        }
    }

    /**
     * Methode zum Abrufen der SOAP
     *
     * @param string $method
     * @param array $params
     * @param boolean $unserialize
     * @return string
     * @throws \Exception
     */
    public function callClient(string $method, array $params = [], $unserialize = false): string
    {
        if (empty($this->login) || empty($this->password)) {
            throw new \Exception("Zugangsdaten wurden noch nicht angelegt");
        }
        if (!isset($this->client)) {
            throw new \Exception('Client failed');
        }

        $params = array_merge([ $this->login, $this->password ], $params);

        $response = $this->client->$method(...$params);

        return $unserialize ? unserialize($response) : $response;
    }
}
