import cloneDeep from 'lodash/cloneDeep';
import { store } from '@/store';
import EmployeeService from '../services/employee.service';
import Employees from '../views/Employees';
import { hasRole } from '@/utils/user';
import UserSelect from '../components/UserSelect.vue';
import DateInput from '../components/DateInput.vue';

export default (context, router) => {
    const employeesContext = cloneDeep(context);
    employeesContext.routerPrefix = 'company/employees';

    const crud = employeesContext.createCrud('employees.crud-title', 'employees', EmployeeService);
    const crudEditRoute = crud.edit.getEditRouteName();
    const crudNewRoute = crud.new.getNewRouteName();

    const navigation = { edit: crudEditRoute, new: crudNewRoute };

    crud.new.addToMetaProperties('permissions', 'employees/create', crud.new.getRouterConfig());
    crud.new.addToMetaProperties('navigation', navigation, crud.new.getRouterConfig());
    crud.new.addToMetaProperties('afterSubmitCallback', () => router.go(-1), crud.new.getRouterConfig());

    crud.edit.addToMetaProperties('permissions', 'employees/edit', crud.edit.getRouterConfig());

    const grid = employeesContext.createGrid('employees.grid-title', 'employees', EmployeeService);
    grid.addToMetaProperties('navigation', navigation, grid.getRouterConfig());
    grid.addToMetaProperties('permissions', () => hasRole(store.getters['user/user'], 'admin'), grid.getRouterConfig());

    // Fields for new employee (user is editable)
    const fieldsForNew = [
        {
            key: 'id',
            displayable: false,
        },
        {
            label: 'field.user',
            key: 'user_id',
            render: (h, props) => {
                const value = Array.isArray(props.currentValue)
                    ? props.currentValue
                    : typeof props.currentValue === 'number'
                      ? [props.currentValue]
                      : [];

                return h(UserSelect, {
                    props: {
                        value,
                        localStorageKey: 'user-select.employee',
                    },
                    on: {
                        change: function (selectedUsers) {
                            // For employee, we only need one user, so take the first one
                            const userId = selectedUsers.length > 0 ? selectedUsers[0] : null;
                            props.inputHandler(userId);
                        },
                    },
                });
            },
            required: true,
        },
        {
            label: 'field.employee_id',
            key: 'employee_id',
            type: 'input',
            required: true,
            placeholder: 'field.employee_id',
        },
        {
            label: 'field.date_of_joined',
            key: 'date_of_joined',
            required: true,
            render: (h, props) => {
                const value = props.currentValue && typeof props.currentValue === 'string' ? props.currentValue : null;

                return h(DateInput, {
                    props: {
                        inputHandler: props.inputHandler,
                        value,
                    },
                });
            },
        },
    ];

    // Fields for edit employee (user is read-only)
    const fieldsForEdit = [
        {
            key: 'id',
            displayable: false,
        },
        {
            label: 'field.user',
            key: 'name', // Display the user name instead of user_id
            render: (h, props) => {
                const name = props.values.name || '';
                const email = props.values.email || '';
                const displayText = email ? `${name} (${email})` : name;
                return h('span', displayText);
            },
        },
        {
            label: 'field.employee_id',
            key: 'employee_id',
            type: 'input',
            required: true,
            placeholder: 'field.employee_id',
        },
        {
            label: 'field.date_of_joined',
            key: 'date_of_joined',
            required: true,
            render: (h, props) => {
                const value = props.currentValue && typeof props.currentValue === 'string' ? props.currentValue : null;

                return h(DateInput, {
                    props: {
                        inputHandler: props.inputHandler,
                        value,
                    },
                });
            },
        },
    ];

    crud.edit.addField(fieldsForEdit);
    crud.new.addField(fieldsForNew);

    grid.addColumn([
        {
            title: 'field.full_name',
            key: 'name',
        },
        {
            title: 'field.email',
            key: 'email',
        },
        {
            title: 'field.employee_id',
            key: 'employee_id',
        },
        {
            title: 'field.date_of_joined',
            key: 'date_of_joined',
            // render(h, { item }) {
            //     return h('span', {}, [
            //         new Date(item.date_of_joined).toLocaleDateString()
            //     ]);
            // },
        },
    ]);

    grid.addAction([
        {
            title: 'control.edit',
            icon: 'icon-edit',
            onClick: (router, { item }, context) => {
                context.onEdit(item);
            },
            renderCondition: ({ $can }, item) => {
                return $can('update', 'employee', item);
            },
        },
        {
            title: 'control.delete',
            actionType: 'error',
            icon: 'icon-trash-2',
            onClick: async (router, { item }, context) => {
                context.onDelete(item);
            },
            renderCondition: ({ $can }, item) => {
                return $can('delete', 'employee', item);
            },
        },
    ]);

    grid.addPageControls([
        {
            label: 'control.create',
            type: 'primary',
            icon: 'icon-edit',
            onClick: ({ $router }) => {
                $router.push({ name: crudNewRoute });
            },
        },
    ]);

    return {
        accessCheck: async () => hasRole(store.getters['user/user'], 'admin'),
        scope: 'company',
        order: 20,
        component: Employees,
        route: {
            name: 'Employees.crud.employees',
            path: '/company/employees',
            meta: {
                label: 'navigation.employees',
                service: new EmployeeService(),
            },
            children: [
                {
                    ...grid.getRouterConfig(),
                    path: '',
                },
                ...crud.getRouterConfig(),
            ],
        },
    };
};
