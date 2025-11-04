const { Application } = Shopware;

import '../core/component/cc-score';
import '../core/component/cc-additionalinfo';
import '../core/component/cc-awareness';

Application.addServiceProviderDecorator('ruleConditionDataProviderService', (ruleConditionService) => {
    ruleConditionService.upsertGroup('adu_cc', {
        id: 'adu_cc',
        name: 'Bonitätsregeln'
    })
    ruleConditionService.addCondition('score', {
        component: 'cc-score',
        label: 'Check+Collect Score',
        scopes: ['global'],
        group: 'adu_cc',
    });


    ruleConditionService.addCondition('additionalinfo', {
        component: 'cc-additionalinfo',
        label: 'Check+Collect Info',
        group: 'adu_cc',
        scopes: ['global']
    });

    ruleConditionService.addCondition('awareness', {
        component: 'cc-awareness',
        label: 'Check+Collect Awareness',
        group: 'adu_cc',
        scopes: ['global']
    });
    return ruleConditionService;
});