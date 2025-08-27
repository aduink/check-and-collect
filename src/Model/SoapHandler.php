<?php
declare(strict_types = 1);
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
        } catch (\SoapFault $e) {
            return $e->getMessage();
        }
    }

    /**
     * Methode zum Abrufen der SOAP
     *
     * @param string $method
     * @param array $params
     * @param boolean $unserialize
     * @return string
     */
    public function callClient(string $method, Array $params = array(), $unserialize = false): string
    {

        try {
            if(!isset($this->client)){
                throw new \Exception('Client failed');
            }

            $params = array_merge(array(
                $this->login,
                $this->password
            ), $params);

            $response = call_user_func_array(array(
                $this->client,
                $method
            ), $params);

            if ($unserialize) {
                $response = unserialize($response, false);
            }
        } catch (\Exception $e) {
            throw $e;
        }

        return $response;
    }
}
