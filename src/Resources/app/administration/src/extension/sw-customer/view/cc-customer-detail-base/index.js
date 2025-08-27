import template from './cc-customer-detail-score.html.twig';

const {Component, Mixin} = Shopware;

Component.register('cc-customer-detail-score', {
    template,
    inject: [ 'solvency' ],
    mixins: [ Mixin.getByName('notification') ],
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
            debtorIcon: 'default-shopping-paper-bag',
            iframeUrl: new URL('https://api.adu-inkasso.de/rpc/static'),
            token: null,
        };
    },
    computed: {
        iframeSrc: {
            get(){
                return this.iframeUrl.toString();
            },
        },
        isDisabled: {
            get(){
                return this.isLoading;
            }
        }
    },

    created() {
        this.solvency.fillIframeData(this.iframeUrl, "/rpc/debtorCreditHistory")
            .then(u => {
                u.searchParams.set('entity', this.customer?.id)
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
    },

    methods: {
        onError(namespace, e){
            console.log("Error", e);
            this.isLoading = false;
            this.iframeUrl.pathname = "/rpc/static";
            const message = e instanceof Error ? e.message : this.$tc(namespace + '.error');

            this.createNotificationError({
                title: this.$tc(namespace + '.title'),
                message,
            });
        },
        getSolvency() {
            if(!this.customer?.id){
                console.log("Keine customer id gefunden")
                return;
            }
            this.isLoading = true;
            this.solvency.solvency(this.customer.id)
                .then(() => {
                    this.createNotificationSuccess({
                        title: this.$tc('cc-api-newsolvency-button.title'),
                        message: this.$tc('cc-api-newsolvency-button.success')
                    });
                    this.isLoading = false;
                    const token = this.token;
                    this.token = null;
                    setTimeout(() => { this.token = token}, 300);
                })
                .catch(e => this.onError('cc-api-newsolvency-button', e));
        }
    }
});
