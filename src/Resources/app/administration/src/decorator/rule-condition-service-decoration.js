const { Application } = Shopware;
import '../core/component/cc-score';
import '../core/component/cc-additionalinfo';

Application.addServiceProviderDecorator('ruleConditionDataProviderService', (ruleConditionService) => {
    ruleConditionService.addCondition('score', {
        component: 'cc-score',
        label: 'Check+Collect Score',
        scopes: ['global']
    });

    ruleConditionService.addCondition('additionalinfo', {
        component: 'cc-additionalinfo',
        label: 'Check+Collect Info',
        scopes: ['global']
    });
    return ruleConditionService;
});

