import template from './cc-customer-detail-score.html.twig';

const {Component, Mixin} = Shopware;

Component.register('cc-customer-detail-score', {
    template,

    inject: [
        'solvency'
    ],

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        customer: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            activeCustomer: this.customer,
            isLoading: false,
            // todo after NEXT-2291: to be removed if new emptyState-Splashscreens are implemented
            debtorIcon: 'default-shopping-paper-bag',
            iframeSrc: 'https://auskunft.adu-inkasso.de/rpclayout/?action=static',
        };
    },

    created() {
        this.solvencyLogin();
    },

    methods: {

        solvencyLogin() {
            let vm = this;
            this.solvency.getToken().then(function(data){
                if(data.error) throw data.error;
                const iframeSrc = 'https://auskunft.adu-inkasso.de/rpclayout/?action=debtorCreditHistory&entity=' + vm.customer.id + '&token=';
                const iframeSrcRedir = iframeSrc.concat('', data.token);
                vm.iframeSrc = iframeSrcRedir;
            }).catch(function(err) {
                const iframeSrcRedir = 'https://auskunft.adu-inkasso.de/rpclayout/?action=debtorCreditHistory&entity=' + vm.customer.id;
                vm.iframeSrc = iframeSrcRedir;
                vm.createNotificationError({
                    title: vm.$tc('cc-api-login.title'),
                    message: vm.$tc('cc-api-login.error')
                });
            });
        },
        onSave() {
            let id = this.customer.id;
            let vm = this;
            vm.isLoading = true;
            this.solvency.solvency(vm.customer.id).then(function(data) {
                if(data == '' || data.error) throw data.error;
                if(data[0].error != '')  throw data[0].error;
                vm.createNotificationSuccess({
                    title: vm.$tc('cc-api-newsolvency-button.title'),
                    message: vm.$tc('cc-api-newsolvency-button.success')
                });
                vm.isLoading = false;

                vm.solvencyLogin();

            }).catch((exception) => {
                vm.isLoading = false;
                vm.createNotificationError({
                    title: vm.$tc('cc-api-newsolvency-button.title'),
                    message: vm.$tc('cc-api-newsolvency-button.error')
                });
            });
        }
    }
});
