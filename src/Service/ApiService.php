<?php
declare(strict_types=1);

namespace Adu\CheckAndCollect\Service;

use Adu\CheckAndCollect\Exception\CCException;
use Adu\CheckAndCollect\Exception\CustomerCannotBeScoredException;
use Adu\CheckAndCollect\Exception\WrongProductRequested;
use Adu\CheckAndCollect\Model\RatingRequest;
use Adu\CheckAndCollect\Model\AduApi;
use Adu\CheckAndCollect\Model\Scoring;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;


/**
 *
 * @author Allgemeiner Debitoren- und Inkassodienst GmbH
 *         Klasse zum Abrufen und aufbereiten der API Daten
 */
class ApiService
{
    use ConfiguredService;

    public function __construct(
        private readonly AduLogger        $logger,
        private readonly AduApi           $api,
        private readonly EntityRepository $customerRepository,
    )
    {
    }

    /**
     * Setzt die Saleschannel ID des Shop-Scopes
     */
    public function setScope(?string $salesChannelId = NULL): void
    {
        $this->config->setSalesChannelId($salesChannelId);
        $this->api->setScope($salesChannelId);
    }


    /**
     * Abrufe des Riskmanagements
     */
    public function getSolvencyCheck(RatingRequest $request): Scoring
    {
        $customer = $request->customer;
        $add = $request->address;
        $this->logger->debug("Getting New SolvencyCheck Form CustomerId: " . $customer->getId());

        $defaultScore = $this->config->defaultCustomerScore();
        try {
            $res = $this->api->getConsumerCheck($request);
            $scoring = new Scoring($res, $defaultScore, $add);
            $this->logger->debug("Scoring: ", context: [$scoring]);
            $this->saveScoring($customer, $scoring);
        } catch (CustomerCannotBeScoredException|WrongProductRequested $e){
            // Diese Exceptions bedeuten, dass der Kunde wie er jetzt ist nicht gescored werden kann
            $this->logger->debug("Kunde kann mit dieser Adresse nicht angefragt werden: ", context: [[
                'message' => $e->getMessage(),
                'description' => $e->describe()
            ]]);
            $scoring = new Scoring([], $defaultScore, $add);
            $this->saveScoring($customer, $scoring);
        } catch (CCException $e) {
            // Andere Exceptions hängen nicht mit dem angefragten Kunden zusammen und könnten jederzeit behoben werden
            // Die können vielleicht in der Session gespeichert werden aber definitiv nicht in den Customfields
            $this->logger->debug("Fehler Bei der Anfrage: ", context: [[
                'message' => $e->getMessage(),
                'description' => $e->describe()
            ]]);
            $scoring = new Scoring([], $defaultScore, $add);
        }
        return $scoring;
    }

    private function saveScoring(CustomerEntity $customer, Scoring $result): void
    {
        $this->updateCustomFields($customer, $result->toArray());
    }
    private function updateCustomFields(CustomerEntity $customer, array $newValues): void
    {
        // Customerfields update
        $customFields = $customer->getCustomFields() ?? [];
        $customFields = array_merge($customFields, $newValues);

        $this->customerRepository->update([['id' => $customer->getId(), 'customFields' => $customFields]], new Context(new SystemSource()));
        $this->logger->debug('Customfields werden aktualisiert.', context: $customFields);
    }

    /**
     * @return array{token:null|string}
     * @throws \Exception
     */
    public function getToken(): array
    {
        $resp = $this->api->request(
            path: '/sw/token'
        );
        return json_decode($resp, true);
    }

    /**
     * @throws \Exception
     */
    public function getCredits(): float
    {
        $resp = $this->api->request(
            path: '/sw/credits'
        );
        $arr = json_decode($resp, true);
        $credits = $arr['credits'] ?? 0;
        if (!is_numeric($credits)) {
            return 0.0;
        }
        return (float)$credits;
    }
}
