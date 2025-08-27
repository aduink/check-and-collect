import SolvencyService from '../services/solvency.service.js';

Shopware.Application.addServiceProvider('solvency', container => {
    const httpClient = Shopware.Application.getContainer('init').httpClient;
    const loginService = Shopware.Application.getContainer('service').loginService;
    return new SolvencyService(httpClient, loginService);
});
