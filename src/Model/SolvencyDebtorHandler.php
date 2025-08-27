<?php
declare(strict_types=1);

namespace Adu\CheckAndCollect\Model;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Context;


class SolvencyDebtorHandler
{

    /**
     * SolvencyDebtorHandler constructor.
     */
    public function __construct(private readonly ContainerInterface $container, private readonly Context $context)
    {
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function formDebtorData(string $id): array
    {
        // Nutzer ermitteln
        /** @var EntityRepository<CustomerEntity> $customerRepository */
        $customerRepository = $this->container->get('customer.repository');
        $customerCriteria = (new Criteria([$id]))
            ->addAssociation('addresses')
            ->addAssociation('addresses.country');

        $result = $customerRepository
            ->search($customerCriteria, $this->context)
            ->getEntities();


        /** @var CustomerEntity $customer */
        $customer = $result->first();

        if($customer === null){
            return [];
        }

        $defaultBillingId = $customer->getDefaultBillingAddressId();
        $addRow = $customer->getAddresses();

        /** @var CustomerAddressEntity $address */
        $address = $addRow
            ->filter(fn(CustomerAddressEntity $a) => $a->getId() === $defaultBillingId)
            ->first();
        if($address === null){
            return [];
        }
        return [
            'firstname' => $address->getFirstName(),
            'lastname' => $address->getLastName(),
            'street' => $address->getStreet(),
            'housenumber' => '',
            'zipcode' => $address->getZipcode(),
            'city' => $address->getCity(),
            'company' => $address->getCompany(),
            'phone' => $address->getPhoneNumber(),
            'email' => $customer->getEmail(),
            'birthday' => $customer->getBirthday()?->format("d.m.Y") ?? '',
            'ordernumber' => $customer->getId(),
            'country' => $address->getCountry()?->getIso(),
            'shopsetting' => [
                'salution' => $address->getSalutationId(),
                'amount' => '',
                'customerEntityId' => $id
            ],
            'customerId' => $customer->getCustomerNumber()
        ];
    }
}
