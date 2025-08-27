// Module
import './module/cc-debtor';

// Regeln
import './decorator/rule-condition-service-decoration';

// CC Kunden Score
import './extension/sw-customer/view/cc-customer-detail-base';
import './extension/sw-customer/page/cc-customer-detail';

// Inits
import './init/solvency-service.init';

// Configbutton
import './component/cc-api-test-button';

import localeDE from './snippet/de_DE.json';
import localeEN from './snippet/en_GB.json';
Shopware.Locale.extend('de-DE', localeDE);
Shopware.Locale.extend('en-GB', localeEN);

// Route für Customer zum Scoring abändern
const { Module } = Shopware;

Module.register('cc-new-tab-score', {
    routeMiddleware(next, currentRoute) {
        if (currentRoute.name === 'sw.customer.detail') {
            currentRoute.children.push({
                name: 'cc.customer.detail.score',
                path: '/sw/customer/detail/:id/score',
                component: 'cc-customer-detail-score',
                meta: {
                    parentPath: 'sw.customer.index'
                }
            });
        }
        next(currentRoute);
    }
});
