import axios from '@/config/app';
import ResourceService from '@/services/resource.service';

export default class EmployeeService extends ResourceService {
    getAll(config = {}) {
        return axios.get('employees/list', config);
    }

    getItemRequestUri(id) {
        return `employees/show?id=${id}`;
    }

    getItem(id, filters = {}) {
        return axios.get(this.getItemRequestUri(id));
    }

    save(data, isNew = false) {
        return axios.post(`employees/${isNew ? 'create' : 'edit'}`, data);
    }

    deleteItem(id) {
        return axios.post('employees/remove', { id });
    }

    getWithFilters(filters, config = {}) {
        return axios.post('employees/list', filters, config);
    }
}
