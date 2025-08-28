<?php

namespace Adu\CheckAndCollect\Model;

use Adu\CheckAndCollect\Exception\B2bRequestNotActivated;
use Adu\CheckAndCollect\Exception\CCException;
use Adu\CheckAndCollect\Exception\InvalidResponse;
use Adu\CheckAndCollect\Exception\NoCredentialsException;
use Adu\CheckAndCollect\Service\ConfiguredService;
use Adu\CheckAndCollect\Service\AduLogger;

class AduApi
{
    use ConfiguredService;

    public function __construct(
        private readonly AduLogger $log,
    )
    {
    }

    private const url = "https://api.adu-inkasso.de";

    /**
     * @throws CCException
     */
    public function request(
        string $method = 'GET',
        string $path = '/',
        array  $headers = [],
        array  $data = [],
        string $returnFormat = "application/json"
    ): string
    {
        if(empty($this->config->login()) || empty($this->config->password())){
            throw new NoCredentialsException("Api kann nicht erreicht werden. Credentials müssen in den Einstellungen gesetzt sein.");
        }
        $headers[] = "Accept: " . $returnFormat;
        $options = [
            CURLOPT_USERPWD => $this->config->login() . ":" . $this->config->password(),
            CURLOPT_URL => self::url . $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => 'SW6 Curl',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
        ];

        if ($method === 'GET') {
            $options[CURLOPT_URL] .= "?" . http_build_query($data);
        } elseif ($method === 'POST') {
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
            $options[CURLOPT_HTTPHEADER][] = "Content-Type: application/json";
        }
        $ch = curl_init();
        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if (gettype($response) !== 'string') {
            $error = curl_error($ch);
            curl_close($ch);
            throw CCException::fromCode($error, $code);
        }
        if ($code >= 300) {
            curl_close($ch);
            $r = json_decode($response, true);
            throw CCException::fromCode($r['message'] ?? $r['error'] ?? $r['status'] ?? $response, $code);
        }
        curl_close($ch);
        return $response;
    }

    /**
     * @throws CCException
     */
    public function getConsumerCheck(RatingRequest $parser): array
    {
        if ($parser->isBusiness() && !$this->config->checkCompany()) {
            throw new B2bRequestNotActivated;
        }
        $this->log->info("ADU-API wird angefragt");

        $response = $this->request(
            "POST",
            "/rating/" . $parser->product,
            data: $parser->toAdu( $this->config )
        );
        try{
            return json_decode($response, true, flags: JSON_THROW_ON_ERROR);
        }catch (\JsonException){
            throw new InvalidResponse($response);
        }
    }
}