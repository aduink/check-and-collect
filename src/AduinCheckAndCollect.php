<?php
declare(strict_types=1);

namespace Adu\CheckAndCollect;

use Adu\CheckAndCollect\Service\AduConfig;
use Adu\CheckAndCollect\Service\RuleFallbackManager;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Context;
use Adu\CheckAndCollect\Service\AduLogger;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldSet;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetEntity;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\DirectoryLoader;
use Symfony\Component\DependencyInjection\Loader\GlobFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Contracts\Service\Attribute\Required;

define('CHECKANDCOLLECTVERSION', '2.7.6');
define('CHECKANDCOLLECTSALT', 'hui3h9T%$T54t$&%)="$&v56');

/**
 *
 * @author brune
 *
 */
class AduinCheckAndCollect extends Plugin
{
    private ?AduLogger $logger = null;
    private ?RuleFallbackManager $fallbackManager = null;
    private ?EntityRepository $customFieldSetRepository = null;
    const customFieldName = "adu_solvencysettings";
    const invoiceFieldName = "adu_invoicenumber";
    public const ruleNames = ['additionalinfo', 'score', 'awareness'];

    const invoiceField = [
        'name' => AduConfig::INVOICE_NUMBER,
        'type' => CustomFieldTypes::TEXT,
        'config' => [
            'label' => [
                'de-DE' => 'Rechnungsnummer',
                'en-GB' => 'Invoice number'
            ]
        ]
    ];
    const customFields = [
        [
            'name' => AduConfig::SKIP_CHECK,
            'type' => CustomFieldTypes::BOOL,
            'config' => [
                'label' => [
                    'de-DE' => 'Der Kunde ist ausgenommen von der Bonitätsprüfung',
                    'en-GB' => 'The customer is exempt from the credit check'
                ]
            ]
        ],
        [
            'name' => AduConfig::SCORE,
            'type' => CustomFieldTypes::FLOAT,
            'config' => [
                'label' => [
                    'de-DE' => 'Score',
                    'en-GB' => 'Score'
                ]
            ]
        ],
        [
            'name' => AduConfig::ADDITIONAL,
            'type' => CustomFieldTypes::TEXT,
            'config' => [
                'label' => [
                    'de-DE' => 'Zusatzinformation',
                    'en-GB' => 'Additional Information'
                ]
            ]
        ],
        [
            'name' => AduConfig::AWARENESS,
            'type' => CustomFieldTypes::TEXT,
            'config' => [
                'label' => [
                    'de-DE' => 'Identifikationscode',
                    'en-GB' => 'Code of identification'
                ]
            ]
        ],
        [
            'name' => AduConfig::LAST_SCORE_TIME,
            'type' => CustomFieldTypes::DATE,
            'config' => [
                'label' => [
                    'de-DE' => 'Datum der letzten Prüfung',
                    'en-GB' => 'Last exam date'
                ]
            ]
        ]
    ];

    /**
     * @throws \Exception
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $locator = new FileLocator("Resources/config");
        $resolver = new LoaderResolver([
            new YamlFileLoader($container, $locator),
            new GlobFileLoader($container, $locator),
            new DirectoryLoader($container, $locator),
        ]);
        $configLoader = new DelegatingLoader($resolver);
        $configDir = rtrim($this->getPath(), '/') . '/Resources/config';
        $configLoader->load($configDir . '/{packages}/*.yaml', 'glob');
    }

    /**
     * @see Plugin::install
     */
    public function install(InstallContext $installContext): void
    {
    }

    public function update(UpdateContext $updateContext): void
    {
        try {
            $this->getLogger()?->warning("Plugin wird geupdated");
            $c = $updateContext->getContext();
            $this->removeCustomFields($c);
            $this->installCustomFields($c);
        } catch (\Exception) {
        }
    }

    /**
     * @param UninstallContext $uninstallContext
     * @see Plugin::uninstall
     */
    public function uninstall(UninstallContext $uninstallContext): void
    {
        $this->getLogger()?->warning("Plugin wird uninstalliert");

        if ($uninstallContext->keepUserData()) {
            return;
        }
        $this->removeCustomFields($uninstallContext->getContext());
        $this->uninstallCustomRules();
        parent::uninstall($uninstallContext);
    }

    public function activate(Plugin\Context\ActivateContext $activateContext): void {
        try {
            $this->getLogger()?->warning("Plugin wird aktiviert");
            $this->getFallbackmanager()
                ?->restoreRules($activateContext->getContext());
            $this->installCustomFields($activateContext->getContext());
        } catch (\Exception $e) {
            $this->getLogger()?->critical($e->getMessage());
        }
    }
    public function deactivate(DeactivateContext $deactivateContext): void
    {
        $this->getLogger()?->warning("Plugin wird deaktiviert");
        $this->getFallbackmanager()
            ?->replaceWithAlwaysValid($deactivateContext->getContext());
        $this->removeCustomFields($deactivateContext->getContext());
    }
    private function getFallbackmanager(): ?RuleFallbackManager {
        $this->fallbackManager ??= (function(){
            $r = $this->container->get(RuleFallbackManager::class);
            if(!$r instanceof RuleFallbackManager) {
                $this->getLogger()?->error("Konnte rulefallbackmanager nicht finden");
                return null;
            }
            return $r;
        })();
        return $this->fallbackManager;
    }

    private function removeCustomFields(Context $context): void
    {
        $this->getLogger()?->warning("Customfields werden entfernt");
        $criteria = (new Criteria())
            ->addFilter(new OrFilter([
                new EqualsFilter('name', self::customFieldName),
                new EqualsFilter('name', self::invoiceFieldName)
            ]));

        $repo = $this->getCustomFieldSetRepository();
        $res = $repo
            ->search($criteria, $context);
        /**
         * @var CustomFieldSetEntity $cfs
         */
        foreach($res as $cfs){
            $this->getLogger()?->warning("Deleting CustomFieldSetEntity : ".$cfs->getName(). " with id: ".$cfs->getId());
            $repo->delete([['id' => $cfs->getUniqueIdentifier()]], $context);
        }
    }

    private function installCustomFields(Context $context): void
    {
        $this->getLogger()?->warning("Customfields werden installiert");
        // Customer
        $this->getCustomFieldSetRepository()->create([
            [
                'name' => self::customFieldName,
                'customFields' => self::customFields,
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
        $this->getCustomFieldSetRepository()->create([
            [
                'name' => self::invoiceFieldName,
                'customFields' => [self::invoiceField],
                'config' => [
                    'label' => [
                        'de-DE' => 'Rechnung',
                        'en-GB' => 'Invoice'
                    ]
                ],
                'relations' => [
                    [
                        'entityName' => 'order'
                    ]
                ]
            ]
        ], $context);
    }

    /**
     * Löscht alle Regeln die Namen aus der Klassen-Konstante "ruleNames" hat.
     * Kann aus uninstall oder Deaktivierung kommen
     */
    private function uninstallCustomRules(): void
    {
        try {
            $this->getLogger()?->warning("Customrules werden deinstalliert");
            /** @var Connection $connection */
            $connection = $this->container->get(Connection::class);
            $connection
                ->executeStatement(
                    "DELETE FROM rule_condition WHERE type IN (:rules)",
                    ['rules' => self::ruleNames],
                    ['rules' => ArrayParameterType::STRING]
                );
        } catch (\Exception|Exception $e) {
            $this->getLogger()?->critical("Could not uninstall custom rules: ". $e->getMessage());
        }
    }

    /**
     * @return EntityRepository<CustomFieldSet>
     */
    private function getCustomFieldSetRepository(): EntityRepository
    {
        $this->customFieldSetRepository ??= $this->container->get('custom_field_set.repository');
        return $this->customFieldSetRepository;
    }
    private function getLogger(): ?AduLogger
    {
        if(!isset($this->logger)){
            $l = $this->container->get(AduLogger::class);
            if($l instanceof AduLogger){
                $this->logger = $l;
            }
        }
        return $this->logger;
    }

    #[Required]
    public function setLogger(AduLogger $logger, RuleFallbackManager $fallbackManager): void
    {
        $this->logger = $logger;
        $this->fallbackManager = $fallbackManager;
    }
}
