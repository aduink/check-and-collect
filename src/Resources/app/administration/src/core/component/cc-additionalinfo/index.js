/// <reference types="@shopware-ag/meteor-admin-sdk" />
import template from './cc-additionalinfo.html.twig';

/** @type {import('@shopware-ag/jest-preset-sw6-admin/@tool/setup-env-for-shopware'))} */
const { Component } = Shopware;

Component.extend('cc-additionalinfo', 'sw-condition-base', {
    template,
    computed: {
        operators() {
            return this.conditionDataProviderService.getOperatorSet('string');
        },
        selectValues() {
            return [
                {
                    label: 'Firma / Person unbekannt (B2B)',
                    value: 9
                },
                {
                    label: 'Firma / Anschrift abweichend (B2B)',
                    value: 10
                },
                {
                    label: 'Firma und Anschrift bekannt (B2B)',
                    value: 6
                },
                {
                    label: 'Mehrere Firmen bekannt (B2B)',
                    value: 11
                },
                {
                    label: 'Person / Anschrift bekannt (DE+B2B)',
                    value: 3
                },
                {
                    label: 'Person / Anschrift abweichend (DE+B2B)',
                    value: 4
                },
                {
                    label: 'Person / Anschrift unbekannt (DE)',
                    value: 1
                },
                {
                    label: 'Person unbekannt / Anschrift bekannt (DE+B2B)',
                    value: 2
                },
                {
                    label: 'Person / Haushalt / Anschrift bekannt (AT+CH)',
                    value: 7
                },
                {
                    label: 'Person / Haushalt unbekannt (AT+CH)',
                    value: 8
                }
            ];
        },

        isAdditionalinfo: {
            get() {
                this.ensureValueExist();

                // Define a standard value
                if (this.condition.value.isAdditionalinfo == null) {
                    this.condition.value.isAdditionalinfo = 1;
                }

                return this.condition.value.isAdditionalinfo;
            },
            set(isAdditionalinfo) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, isAdditionalinfo };
            }
        }
    }
});
