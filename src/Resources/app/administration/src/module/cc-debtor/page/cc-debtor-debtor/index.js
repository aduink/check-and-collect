import template from './cc-debtor-debtor.html.twig';

const { Component, Mixin } = Shopware;


Component.register('cc-debtor-debtor', {
    template,
	inject: [
        'solvency'
    ],
    mixins: [
        Mixin.getByName('notification')
    ],
    data() {
        return {
            iframeSrc: 'https://auskunft.adu-inkasso.de/rpclayout/?action=static',
        }
    },
    created() {
        this.solvencyLogin()
    },
	methods: {
        solvencyLogin() {
            let vm = this;
            this.solvency.getToken().then(function(data){
                if(data.error) throw data.error;
                const iframeSrc = 'https://auskunft.adu-inkasso.de/rpclayout/?action=debtor&token=';
                const iframeSrcRedir = iframeSrc.concat('', data.token);
                vm.iframeSrc = iframeSrcRedir;
                vm.createNotificationSuccess({
                    title: vm.$tc('cc-api-test-button.title'),
                    message: vm.$tc('cc-api-test-button.success')
                });
            }).catch(function(err) {
                const iframeSrcRedir = 'https://auskunft.adu-inkasso.de/rpclayout/?action=debtor';
                vm.iframeSrc = iframeSrcRedir;
                vm.createNotificationError({
                    title: vm.$tc('cc-api-login.title'),
                    message: vm.$tc('cc-api-login.error')
                });
            });
        }
	}
});

