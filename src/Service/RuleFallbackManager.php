<?php

namespace Adu\CheckAndCollect\Service;

use Adu\CheckAndCollect\AduinCheckAndCollect;
use Shopware\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class RuleFallbackManager
{
    private const BACKUP_KEY = 'adu_rule_backup';

    public function __construct(
        private readonly EntityRepository $ruleConditionRepository,
        private readonly AduLogger $logger
    ){}
    public function replaceWithAlwaysValid(Context $context): void
    {
        $this->logger->debug("Alle Regeln werden mit always valid ersetzt");
        $criteria = (new Criteria())
            ->addFilter(new EqualsAnyFilter('type', AduinCheckAndCollect::ruleNames));

        $updates = $this->ruleConditionRepository
            ->search($criteria, $context)
            ->getEntities()
            ->map(function (RuleConditionEntity $condition) {
                $customFields = $condition->getCustomFields() ?? [];
                $customFields[self::BACKUP_KEY] = json_encode([
                    'type' => $condition->getType(),
                    'value' => $condition->getValue(),
                ], JSON_THROW_ON_ERROR);
                return [
                    'id' => $condition->getId(),
                    'type' => AlwaysValidRule::RULE_NAME,
                    'value' => null,
                    'customFields' => $customFields,
                ];
            });

        $this->logger->debug("NEUE REGELN", [$updates]);
        if($updates){
            $this->ruleConditionRepository->update(array_values($updates), $context);
        }
    }
    public function restoreRules(Context $context): void
    {
        $this->logger->debug("Regeln werden restored");
        $criteria = (new Criteria())
            ->addFilter(
                new EqualsFilter('type', AlwaysValidRule::RULE_NAME),
            );

        $updates = $this->ruleConditionRepository
            ->search($criteria, $context)
            ->getEntities()
            ->filter(fn(RuleConditionEntity $condition) => !empty($condition->getCustomFieldsValue(self::BACKUP_KEY)))
            ->map(function(RuleConditionEntity $condition) {
                $customFields = $condition->getCustomFields();
                $backup = json_decode($customFields[self::BACKUP_KEY], true);
                unset($customFields[self::BACKUP_KEY]);

                return [
                    'id' => $condition->getId(),
                    'type' => $backup['type'],
                    'value' => $backup['value'],
                    'customFields' => $customFields,
                ];
            });
        $this->logger->debug("NEUE REGELN", [$updates]);

        if($updates){
            $this->ruleConditionRepository->update(array_values($updates), $context);
        }
    }
}
