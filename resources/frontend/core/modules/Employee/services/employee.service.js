import axios from '@/config/app';
import ResourceService from '@/services/resource.service';

export default class EmployeeService extends ResourceService {
    async getAll(config = {}) {
        return (await axios.get('v1/employees', config)).data.data;
    }

    getItemRequestUri(id) {
        return `v1/employees/${id}`;
    }

    getItem(id, filters = {}) {
        return axios.get(this.getItemRequestUri(id));
    }

    save(data, isNew = false) {
        const url = isNew ? 'v1/employees' : `v1/employees/${data.id}`;
        const method = isNew ? 'post' : 'put';
        return axios[method](url, data);
    }

    deleteItem(id) {
        return axios.delete(`v1/employees/${id}`);
    }

    getWithFilters(filters, config = {}) {
        return axios.get('v1/employees', { ...config, params: filters });
    }
}
