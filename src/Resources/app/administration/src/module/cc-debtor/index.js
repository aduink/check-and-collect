import './page/cc-debtor-dash';
import './page/cc-debtor-debtor';

const { Module } = Shopware;

Module.register('cc-debtor', {
    type: 'plugin',
    name: 'Check+Collect',
    title: 'Check+Collect Auskünfte',
    description: 'sw-property.general.descriptionTextModule',
    color: '#ff3d58',
    icon: 'default-avatar-single',

    routes: {
        dash: {
            component: 'cc-debtor-dash',
            path: 'dash',
            meta: {
                parentPath: 'cc.debtor.dash'
            }
        },
        debtor: {
            component: 'cc-debtor-debtor',
            path: 'debtor',
            meta: {
                parentPath: 'cc.debtor.debtor'
            }
        },
    },

    navigation: [
    {
    	id: 'cc-dash',
        label: 'Check+Collect',
        color: '#FB8B01',
        path: 'cc.debtor.dash',
        parent: 'sw-dashboard',
        icon: 'default-avatar-single',
        position: 100
    },
    {
        id: 'cc-debtor',
        label: 'Check+Collect',
        color: '#57D9A3',
        path: 'cc.debtor.debtor',
        parent: 'sw-customer'
    }]
});
