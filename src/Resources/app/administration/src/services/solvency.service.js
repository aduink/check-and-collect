const ApiService = Shopware.Classes.ApiService;

class SolvencyService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = '') {
        super(httpClient, loginService, apiEndpoint);
    }

    get(path) {
        const apiRoute = this.getApiBasePath() + path;
        return this.httpClient.get(
            apiRoute,
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    post(path, data) {
        const apiRoute = this.getApiBasePath() + path;
        return this.httpClient.post(
            apiRoute,
            data,
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    solvency(request) {
        return this.post('_action/adu/solvency-api-request',{'id': request});
    }

    getToken(){
        console.log('LADE TOKEN');
        let token = this.post('_action/adu/get-token');
        return token;
    }

    getCredits(){
        return this.post('_action/adu/get-credits');
    }
}

export default SolvencyService;

