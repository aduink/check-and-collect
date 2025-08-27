import template from './cc-debtor-dash.html.twig';

const { Component, Mixin } = Shopware;

Component.register('cc-debtor-dash', {
    template,
    inject: [
        'solvency'
    ],
    mixins: [
        Mixin.getByName('notification')
    ],
    data() {
        return {
            iframeUrl: new URL('https://api.adu-inkasso.de/rpc/static'),
        };
    },
    computed: {
        iframeSrc: {
            get(){
                return this.iframeUrl.toString();
            },
        },
    },
    created() {
        this.solvency.fillIframeData(this.iframeUrl, "/rpc")
            .then(u => {
                this.iframeUrl = u;
                this.createNotificationSuccess({
                    title: this.$tc('cc-api-test-button.title'),
                    message: this.$tc('cc-api-test-button.success')
                });
            })
            .catch(e => {
                const message = e instanceof Error ? e.message : this.$tc('cc-api-login.error');
                this.createNotificationError({
                    title: this.$tc('cc-api-login.title'),
                    message
                });
            })
    }
});
