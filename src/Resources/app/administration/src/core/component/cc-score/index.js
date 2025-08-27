import template from './cc-score.html.twig';
const { Component } = Shopware;


Component.extend('cc-score', 'sw-condition-base', {
    template,
    computed: {
        fieldNames() {
            return ['isScore'];
        },
        operators() {
            return this.conditionDataProviderService.getOperatorSet('number');
        },
        isScore: {
            get() {
                this.ensureValueExist();
                return parseFloat(this.condition.value.isScoreValue);
            },
            set(isScoreValue) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, isScoreValue };
            }
        },
    },
});
