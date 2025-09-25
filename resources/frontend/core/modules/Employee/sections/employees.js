import cloneDeep from 'lodash/cloneDeep';
import { store } from '@/store';
import EmployeeService from '../services/employee.service';
import Employees from '../views/Employees';
import ColorInput from '@/components/ColorInput';
import { hasRole } from '@/utils/user';

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

    const fieldsToFill = [
        {
            key: 'id',
            displayable: false,
        },
        {
            label: 'field.name',
            key: 'name',
            type: 'input',
            required: true,
            placeholder: 'field.name',
        },
        {
            label: 'field.color',
            key: 'color',
            required: false,
            render: (h, data) => {
                return h(ColorInput, {
                    props: {
                        value: typeof data.currentValue === 'string' ? data.currentValue : 'transparent',
                    },
                    on: {
                        change(value) {
                            data.inputHandler(value);
                        },
                    },
                });
            },
        },
    ];

    crud.edit.addField(fieldsToFill);
    crud.new.addField(fieldsToFill);

    grid.addColumn([
        {
            title: 'field.name',
            key: 'name',
        },
        {
            title: 'field.color',
            key: 'color',
            render(h, { item }) {
                return h(
                    'span',
                    {
                        style: {
                            display: 'flex',
                            alignItems: 'center',
                        },
                    },
                    [
                        h('span', {
                            style: {
                                display: 'inline-block',
                                background: item.color,
                                borderRadius: '4px',
                                width: '16px',
                                height: '16px',
                                margin: '0 4px 0 0',
                            },
                        }),
                        h('span', {}, [item.color]),
                    ],
                );
            },
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
