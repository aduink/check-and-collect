<?php
declare(strict_types=1);

namespace Adu\CheckAndCollect;

use Adu\CheckAndCollect\Service\AduConfig;
use Shopware\Core\Framework\Context;
use Adu\CheckAndCollect\Service\AduLogger;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldSet;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
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

define('CHECKANDCOLLECTVERSION', '2.7.3');
define('CHECKANDCOLLECTSALT', 'hui3h9T%$T54t$&%)="$&v56');

/**
 *
 * @author brune
 *
 */
class AduinCheckAndCollect extends Plugin
{
    private ?AduLogger $logger = null;
    private ?EntityRepository $customFieldSetRepository = null;
    const customFieldName = "adu_solvencysettings";
    const ruleNames = ['additionalinfo', 'score', 'awareness'];

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
        try {
            $this->installCustomFields($installContext->getContext());
        } catch (\Exception $e) {
            $this->logger?->critical($e->getMessage());
        }
    }

    public function update(UpdateContext $updateContext): void
    {
        try {
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

        if ($uninstallContext->keepUserData()) {
            return;
        }
        $this->removeCustomFields($uninstallContext->getContext());
        $this->uninstallCustomRules();
        parent::uninstall($uninstallContext);
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        $this->uninstallCustomRules();
    }

    private function removeCustomFields(Context $context): void
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('name', self::customFieldName));

        $repo = $this->getCustomFieldSetRepository();

        $id = $repo
            ->search($criteria, $context)
            ->first()
            ?->getUniqueIdentifier();

        if ($id) {
            $repo->delete([['id' => $id]], $context);
        }
    }

    private function installCustomFields(Context $context): void
    {
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
    }

    /**
     * Löscht alle Regeln die Namen aus der Klassen-Konstante "ruleNames" hat.
     * Kann aus uninstall oder deaktivierung kommen
     */
    private function uninstallCustomRules(): void
    {
        try {
            /** @var Connection $connection */
            $connection = $this->container->get(Connection::class);
            $content = implode(",", array_map(fn() => "?", self::ruleNames));

            $connection
                ->prepare("DELETE FROM rule_condition WHERE type IN ($content)")
                ->executeQuery(self::ruleNames);
        } catch (\Exception $e) {
            $this->logger?->critical($e->getMessage());
            return;
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

    #[Required]
    public function setLogger(AduLogger $logger): void
    {
        $this->logger = $logger;
    }
}
