import template from './wg-dunning-list.html.twig';

const { Component, Mixin } = Shopware;

Component.register('wg-dunning-list', {
    template,

    inject: ['loginService'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            dunnings: [],
            isLoading: true,
            showCreateModal: false,
            isCreating: false,
            createOrderId: '',
        };
    },

    created() {
        this.loadDunnings();
    },

    methods: {
        async loadDunnings() {
            this.isLoading = true;
            try {
                const headers = {
                    ...this.loginService.getHeader(),
                    Accept: 'application/json',
                };
                const apiPath = Shopware.Context.api.apiPath || '/api';
                const response = await fetch(`${apiPath}/wg-dunning/list`, { headers });
                const data = await response.json();
                this.dunnings = data.data || [];
            } catch (error) {
                this.createNotificationError({ message: error.message });
            } finally {
                this.isLoading = false;
            }
        },

        openCreateModal() {
            this.showCreateModal = true;
            this.createOrderId = '';
        },

        async onCreateDunning() {
            if (!this.createOrderId) return;

            this.isCreating = true;
            try {
                const headers = {
                    ...this.loginService.getHeader(),
                    'Content-Type': 'application/json',
                };
                const apiPath = Shopware.Context.api.apiPath || '/api';
                const response = await fetch(`${apiPath}/wg-dunning/create`, {
                    method: 'POST',
                    headers,
                    body: JSON.stringify({ orderId: this.createOrderId }),
                });

                const result = await response.json();

                if (result.success) {
                    this.createNotificationSuccess({
                        message: this.$tc('wg-dunning.list.successCreated')
                            .replace('{level}', result.dunning.level)
                            .replace('{fee}', result.dunning.fee.toFixed(2)),
                    });
                    this.showCreateModal = false;
                    await this.loadDunnings();
                } else {
                    this.createNotificationError({
                        message: this.$tc('wg-dunning.list.errorCreate') + ': ' + (result.message || ''),
                    });
                }
            } catch (error) {
                this.createNotificationError({
                    message: this.$tc('wg-dunning.list.errorCreate') + ': ' + error.message,
                });
            } finally {
                this.isCreating = false;
            }
        },

        async onDownloadPdf(dunningId) {
            try {
                const headers = {
                    ...this.loginService.getHeader(),
                };
                const apiPath = Shopware.Context.api.apiPath || '/api';
                const response = await fetch(`${apiPath}/wg-dunning/${dunningId}/pdf`, { headers });

                if (!response.ok) {
                    throw new Error(`PDF-Fehler (HTTP ${response.status})`);
                }

                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = `Mahnung-${dunningId.substring(0, 8)}.pdf`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                window.URL.revokeObjectURL(url);
            } catch (error) {
                this.createNotificationError({
                    message: this.$tc('wg-dunning.list.errorPdf') + ': ' + error.message,
                });
            }
        },

        getLevelLabel(level) {
            const labels = {
                1: this.$tc('wg-dunning.list.level1'),
                2: this.$tc('wg-dunning.list.level2'),
                3: this.$tc('wg-dunning.list.level3'),
            };
            return labels[level] || `Stufe ${level}`;
        },

        formatDate(dateStr) {
            if (!dateStr) return '–';
            return new Date(dateStr).toLocaleDateString('de-CH');
        },

        formatCurrency(amount) {
            if (amount === null || amount === undefined) return '–';
            return `CHF ${parseFloat(amount).toFixed(2)}`;
        },
    },
});
