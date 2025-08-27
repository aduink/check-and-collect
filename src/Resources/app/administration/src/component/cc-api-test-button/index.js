const { Component, Mixin } = Shopware;
import template from './cc-api-test-button.html.twig';

Component.register('cc-api-test-button', {
    template,

    props: ['label'],
    inject: ['solvency'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
        };
    },

    computed: {
        pluginConfig() {
            let $parent = this.$parent;

            while ($parent.actualConfigData === undefined) {
                $parent = $parent.$parent;
            }

            return $parent.actualConfigData.null;
        }
    },

    methods: {
        saveFinish() {
            this.isSaveSuccessful = false;
        },

        check() {
            this.isLoading = true;
            let vm = this;
            this.solvency.getCredits(this.pluginConfig).then((res) => {
                if (res.error) throw 'Keine Verbindung zum Service';
                if ( res >= 0 ) {
                    vm.isSaveSuccessful = true;
                    vm.createNotificationSuccess({
                        title: vm.$tc('cc-api-test-button.title'),
                        message: vm.$tc('cc-api-test-button.success')
                    });
                } else {
                    vm.createNotificationError({
                        title: vm.$tc('cc-api-test-button.title'),
                        message: vm.$tc('cc-api-test-button.error')
                    });
                }

                vm.isLoading = false;
            }).catch(function(err) {
                vm.createNotificationError({
                    title: vm.$tc('cc-api-test-button.title'),
                    message: vm.$tc('cc-api-test-button.error')
                });
                vm.isLoading = false;
            });
        }
    }
})
