import { hasRole } from '@/utils/user';

export default class EmployeePolicy {
    static viewAny(user) {
        return hasRole(user, 'admin');
    }

    static create(user) {
        return hasRole(user, 'admin');
    }

    static update(user, employee) {
        return hasRole(user, 'admin');
    }

    static delete(user, employee) {
        return hasRole(user, 'admin');
    }
}
