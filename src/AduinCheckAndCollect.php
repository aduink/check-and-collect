<?php
declare(strict_types = 1);
namespace Adu\CheckAndCollect;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

define('CHECKANDCOLLECTVERSION', '2.5.17');
define('CHECKANDCOLLECTSALT', 'hui3h9T%$T54t$&%)="$&v56');

/**
 * @author brune
 */
class AduinCheckAndCollect extends Plugin
{

    /**
     * @see \Shopware\Core\Framework\Plugin::install()
     */
    public function install(InstallContext $installContext): void
    {
        // Customfieldset
        try {
            $this->installCustomFields($installContext->getContext());
        }catch (\Exception $e){
            // For keepUserData()
        }
    }

    /**
     * @param UninstallContext $uninstallContext
     * @see \Shopware\Core\Framework\Plugin::uninstall()
     */
    public function uninstall(UninstallContext $uninstallContext): void
    {

        if($uninstallContext->keepUserData()){
            return;
        }
        $this->uninstallCustomFields($uninstallContext);
        $this->uninstallCustomRules();
        parent::uninstall($uninstallContext);
    }

    /**
     * @param DeactivateContext $deactivateContext
     */
    public function deactivate(DeactivateContext $deactivateContext): void
    {
        $this->uninstallCustomRules($deactivateContext);
    }

    /**
     * Methode zum installieren von Fieldsets
     *
     * @param $context
     */
    private function installCustomFields($context): void
    {

        // your code you need to execute while installation
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');

        try {
            // Customer
            $customFieldSetRepository->create([
                [
                    'name' => 'adu_solvencysettings',
                    'customFields' => [
                        [
                            'name' => 'deny_solvencycheck_user',
                            'type' => CustomFieldTypes::BOOL,
                            'config' => [
                                'label' => [
                                    'de-DE' => 'Der Kunde ist ausgenommen von der Bonitätsprüfung',
                                    'en-GB' => 'The customer is exempt from the credit check'
                                ]
                            ]
                        ],
                        [
                            'name' => 'adu_score_value',
                            'type' => CustomFieldTypes::FLOAT,
                            'config' => [
                                'label' => [
                                    'de-DE' => 'Score',
                                    'en-GB' => 'Score'
                                ]
                            ]
                        ],
                        [
                            'name' => 'adu_additional_value',
                            'type' => CustomFieldTypes::TEXT,
                            'config' => [
                                'label' => [
                                    'de-DE' => 'Zusatzinformation',
                                    'en-GB' => 'Additional Information'
                                ]
                            ]
                        ]
                    ],
                    'config' => [
                        'label' => [
                            'de-DE' => 'Check+Collect',
                            'en-GB' => 'Check+Collect'
                        ]
                    ],
                    'relations' => [
                        [
                            'entityName' => 'customer'
                        ]
                    ]
                ]
            ], $context);
        } catch (\Exception $e) {
            return ;
        }
    }
    /**
     * @param $context
     * Kann aus uninstall oder deaktivierung kommen
     */
    private function uninstallCustomRules(): void
    {
        try {
            /** @var Connection $connection */
            $connection = $this->container->get(Connection::class);
            $sql = "DELETE FROM rule_condition WHERE type = ? OR type = ? ;";
            $stmt = $connection->prepare($sql);
            $stmt->bindValue(1, 'additionalinfo');
            $stmt->bindValue(2, 'score');
            $stmt->executeQuery();
        }catch (\Exception $e){
            return ;
        }
    }

    /**
     * @param UninstallContext $context
     */
    private function uninstallCustomFields(UninstallContext $context): void
    {
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'adu_solvencysettings'));
        $ids = $customFieldSetRepository->searchIds($criteria, $context->getContext());

        if ($ids) {
            $customFieldSetRepository->delete([
                [
                    'id' => $ids->getIds()[0]
                ]
            ], $context->getContext());
        }
    }
}
