<?php
declare(strict_types = 1);
namespace Adu\CheckAndCollect\Model;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;


class SolvencyDebtorHandler
{
    private $container;
    private $context;

    /**
     * SolvencyDebtorHandler constructor.
     * @param $container
     * @param $context
     */
    public function __construct($container, $context)
    {
        $this->container = $container;
        $this->context = $context;
    }

    /**
     * @param $id
     * @return array
     */
    public function formDebtorData($id): array
    {
        // Nutzer ermitteln
        $customerRepository = $this->container->get('customer.repository');
        $customerCriteria = (new Criteria([$id]))->addAssociation('addresses')->addAssociation('addresses.country');
        $result = $customerRepository->search($customerCriteria, $this->context)->getEntities();
        $customers = (version_compare(PHP_VERSION, '8.1.0', '<')) ? current($result) : $result;
        $addressArr = [];


        // Customer Object
        foreach($result AS $customer){

            $defaultBillingId = $customer->getDefaultBillingAddressId();
            $addRow = $customer->getAddresses();

            // Default Billing selektieren
            foreach($addRow AS $address){
                // Default Zahler des Kunde wird gescored
                if($address->getID() == $defaultBillingId){
                    #$birthday = strftime("%d.%m.%Y", strtotime($customer->getBirthday()->date)); //@todo: bug in 6.3.5
                    $birthday = '';
                    $shopsetting = ['salution' => $address->getSalutationId(), 'amount' => '', 'customerEntityId' => $id];
                    $addressArr = [
                        'firstname' => $address->getFirstName(),
                        'lastname' => $address->getLastName(),
                        'street' => $address->getStreet(),
                        'housenumber' => '',
                        'zipcode' => $address->getZipcode(),
                        'city' => $address->getCity(),
                        'company' => $address->getCompany(),
                        'phone' => $address->getPhoneNumber(),
                        'email' => $customer->getEmail(),
                        'birthday' =>  $birthday,
                        'ordernumber' => $customer->getId(),
                        'country' => ($address->getCountry())->getIso(),
                        'shopsetting' => $shopsetting,
                        'customerId' => $customer->getCustomerNumber()
                    ];
                }
            }
        }

        return $addressArr;
    }
}
