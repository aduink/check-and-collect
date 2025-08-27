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

        async check() {
            const title = this.$tc('cc-api-test-button.title');
            try{
                // soso
                this.isLoading = true;
                /** @var {number} res */
                const res = await this.solvency.getCredits();
                this.isSaveSuccessful = true;
                this.createNotificationSuccess({
                    title,
                    message: this.$tc('cc-api-test-button.success')
                });
            }catch (e){
                this.isSaveSuccessful = false;
                this.createNotificationError({
                    title,
                    message: e.message
                });
            }finally {
                this.isLoading = false;
            }
        }
    }
})
