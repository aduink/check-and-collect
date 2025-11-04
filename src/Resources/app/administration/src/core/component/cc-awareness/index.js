import template from './cc-awareness.html.twig';

const { Component } = Shopware;

Component.extend('cc-awareness', 'sw-condition-base', {
    template,
    computed: {
        fieldNames() {
            return ['isCode'];
        },
        operators() {
            console.log("in operators")
            return this.conditionDataProviderService.getOperatorSet('string');
        },
        selectValues() {
            console.log("in select")
            return [
                {
                    label: 'Bekannt',
                    value: 1
                },
                {
                    label: 'Unklar',
                    value: 2
                },
                {
                    label: 'Unbekannt',
                    value: 3
                },
            ];
        },

        isCode: {
            get() {
                this.ensureValueExist();

                // Define a standard value
                if (this.condition.value.isCode == null) {
                    this.condition.value.isCode = 1;
                }

                return this.condition.value.isCode;
            },
            set(isCode) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, isCode };
            }
        }
    }
});
