<template>
    <div class="user-select" :class="{ 'at-select--visible': showPopup }" @click="togglePopup">
        <at-input
            class="user-select-input"
            :readonly="true"
            :value="inputValue"
            :placeholder="$t('field.select_user')"
            :size="size"
        />

        <span v-show="selectedUserId" class="user-select__clear icon icon-x at-select__clear" @click="clearSelection" />

        <span class="icon icon-chevron-down at-select__arrow" />

        <transition name="slide-up">
            <div v-show="showPopup" class="at-select__dropdown at-select__dropdown--bottom" @click.stop>
                <div class="user-search">
                    <at-input
                        ref="searchInput"
                        v-model="searchValue"
                        class="user-search-input"
                        :placeholder="$t('control.search')"
                    />
                </div>

                <div class="user-select-list">
                    <preloader v-if="isLoading"></preloader>
                    <ul v-else>
                        <li
                            v-for="user in filteredUsers"
                            :key="user.id"
                            :class="{
                                'user-select-item': true,
                                active: selectedUserId === user.id,
                            }"
                            @click="selectUser(user.id)"
                        >
                            <UserAvatar class="user-avatar" :size="25" :borderRadius="5" :user="user" />

                            <div class="user-name">{{ user.full_name }} ({{ user.email }})</div>
                        </li>
                        <li v-if="filteredUsers.length === 0" class="no-users">
                            {{ $t('field.no_available_users') }}
                        </li>
                    </ul>
                </div>
            </div>
        </transition>
    </div>
</template>

<script>
    import UserAvatar from '@/components/UserAvatar';
    import Preloader from '@/components/Preloader';
    import EmployeeService from '../services/employee.service';

    export default {
        name: 'AvailableUserSelect',
        components: {
            UserAvatar,
            Preloader,
        },
        props: {
            value: {
                required: false,
                default: null,
            },
            size: {
                type: String,
                default: 'normal',
            },
        },
        data() {
            return {
                showPopup: false,
                selectedUserId: this.value,
                employeeService: new EmployeeService(),
                searchValue: '',
                availableUsers: [],
                isLoading: false,
            };
        },
        computed: {
            inputValue() {
                if (!this.selectedUserId) {
                    return '';
                }
                const user = this.availableUsers.find(u => u.id === this.selectedUserId);
                return user ? `${user.full_name} (${user.email})` : '';
            },
            filteredUsers() {
                if (!this.searchValue) {
                    return this.availableUsers;
                }

                const searchTerm = this.searchValue.toLowerCase();
                return this.availableUsers.filter(user => {
                    const fullName = user.full_name.toLowerCase();
                    const email = user.email.toLowerCase();
                    return fullName.includes(searchTerm) || email.includes(searchTerm);
                });
            },
        },
        watch: {
            value(newValue) {
                this.selectedUserId = newValue;
            },
        },
        async created() {
            window.addEventListener('click', this.hidePopup);
            await this.loadAvailableUsers();
        },
        beforeDestroy() {
            window.removeEventListener('click', this.hidePopup);
        },
        methods: {
            async loadAvailableUsers() {
                this.isLoading = true;
                try {
                    const response = await this.employeeService.getAvailableUsers();
                    this.availableUsers = response.data;
                } catch (error) {
                    console.error('Failed to load available users:', error);
                    this.availableUsers = [];
                }
                this.isLoading = false;
            },
            togglePopup() {
                this.showPopup = !this.showPopup;
                if (this.showPopup) {
                    this.$nextTick(() => {
                        if (this.$refs.searchInput) {
                            this.$refs.searchInput.$el.querySelector('input').focus();
                        }
                    });
                }
            },
            hidePopup(event) {
                if (!this.$el.contains(event.target)) {
                    this.showPopup = false;
                }
            },
            selectUser(userId) {
                this.selectedUserId = userId;
                this.showPopup = false;
                this.$emit('change', userId);
            },
            clearSelection(event) {
                event.stopPropagation();
                this.selectedUserId = null;
                this.$emit('change', null);
            },
        },
    };
</script>

<style lang="scss" scoped>
    .user-select {
        position: relative;
        cursor: pointer;

        &.at-select--visible {
            .at-select__arrow {
                transform: rotate(180deg);
            }
        }

        .user-select-input {
            pointer-events: none;
        }

        .user-select__clear {
            position: absolute;
            top: 50%;
            right: 24px;
            transform: translateY(-50%);
            cursor: pointer;
            z-index: 10;

            &:hover {
                color: #ff5569;
            }
        }

        // .at-select__arrow {
        //     position: absolute;
        //     top: 50%;
        //     right: 8px;
        //     transform: translateY(-50%);
        //     transition: transform 0.2s ease;
        //     pointer-events: none;
        // }

        // .at-select__dropdown {
        //     position: absolute;
        //     top: 100%;
        //     left: 0;
        //     right: 0;
        //     background: white;
        //     border: 1px solid #eeeef5;
        //     border-radius: 5px;
        //     box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        //     z-index: 1000;
        //     max-height: 300px;
        //     overflow: hidden;
        // }

        .user-search {
            padding: 8px;
            border-bottom: 1px solid #eeeef5;

            .user-search-input {
                width: 100%;
            }
        }

        .user-select-list {
            max-height: 250px;
            overflow-y: auto;

            ul {
                list-style: none;
                padding: 0;
                margin: 0;
            }

            .user-select-item {
                display: flex;
                align-items: center;
                padding: 8px 12px;
                cursor: pointer;
                border-bottom: 1px solid #f5f5f5;

                &:hover,
                &.active {
                    background-color: #f8f9fa;
                }

                .user-avatar {
                    margin-right: 8px;
                }

                .user-name {
                    flex: 1;
                    font-size: 14px;
                }
            }

            .no-users {
                padding: 12px;
                text-align: center;
                color: #999;
                font-style: italic;
            }
        }
    }
</style>
