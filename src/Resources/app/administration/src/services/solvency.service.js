const ApiService = Shopware.Classes.ApiService;

class SolvencyService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = '') {
        super(httpClient, loginService, apiEndpoint);
    }

    get(path) {
        const apiRoute = this.getApiBasePath() + path;
        console.log("GET-Request to ", apiRoute);
        return this.httpClient.get(
            apiRoute,
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            console.log("Response ", response);
            return ApiService.handleResponse(response);
        });
    }

    post(path, data) {
        const apiRoute = this.getApiBasePath() + path;
        console.log("Request to ", apiRoute);
        return this.httpClient.post(
            apiRoute,
            data,
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            console.log("Response ", response);
            return ApiService.handleResponse(response);
        });
    }

    /**
     * @param pr
     * @returns {Promise<*>}
     */
    async handle(pr){
        let resp;
        try{
            resp = await pr;
        }catch (e){
            if(e instanceof Error && e.name === "AxiosError" && e.response.data){
                 resp = e.response.data;
            }else{
                throw e;
            }
        }
        this.checkError(resp);
        return resp;
    }

    async solvency(request) {
        return this.handle(
            this.post('_action/adu/solvency-api-request', {'id': request})
        );
    }

    /**
     * @return {Promise<{shop:string,cc:string}>}
     */
    async getVersions() {
        return this.handle(
            this.get('_action/adu/version-request')
        );
    }

    /**
     * @return {Promise<string>}
     */
    async getToken() {
        console.log('LADE TOKEN');
        const tokenResp = await this.handle(this.post('_action/adu/get-token'));
        console.log(
            "Token response",
            tokenResp
        );
        if(!tokenResp.token){
            throw new Error("Kein token zurückgegeben");
        }
        return tokenResp.token;
    }

    getCredits() {
        return this.handle(this.post('_action/adu/get-credits'));
    }

    checkError(data) {
        if(Array.isArray(data.errors) && data.errors.length ){
            const e = data.errors.pop();
            if(typeof e === 'object' && 'detail' in e){
                throw new Error(e.detail);
            }else{
                throw new Error(e);
            }
        }
        if (data.error) {
            throw new Error(data.error);
        }
        if (data[0]?.error) {
            throw new Error(data[0].error);
        }
    }
    /**
     * @param {URL} url
     * @param {string} suc_path
     * @return {Promise<URL>}
     */
    async fillIframeData(url, suc_path){
        const ret = new URL(url);

        const tokenReq = this.getToken();
        const versionReq = this.getVersions();

        const token = await tokenReq;
        const versions = await versionReq;
        ret.searchParams.set('token', token);
        ret.searchParams.set('shop', versions.shop);
        ret.searchParams.set('cc', versions.cc);
        ret.pathname = suc_path;
        return ret;
    }
}

export default SolvencyService;

