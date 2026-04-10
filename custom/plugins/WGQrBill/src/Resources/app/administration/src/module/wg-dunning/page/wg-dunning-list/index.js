import template from './wg-dunning-list.html.twig';

const { Component, Mixin } = Shopware;

Component.register('wg-dunning-list', {
    template,

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

    computed: {
        httpClient() {
            return Shopware.Application.getContainer('init').httpClient;
        },

        headers() {
            return {
                Accept: 'application/json',
                Authorization: `Bearer ${Shopware.Context.api.authToken.access}`,
                'Content-Type': 'application/json',
            };
        },
    },

    created() {
        this.loadDunnings();
    },

    methods: {
        async loadDunnings() {
            this.isLoading = true;
            try {
                const response = await this.httpClient.get('/_action/wg-dunning/list', {
                    headers: this.headers,
                });
                this.dunnings = response.data.data || [];
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
                const response = await this.httpClient.post('/_action/wg-dunning/create', {
                    orderId: this.createOrderId,
                }, {
                    headers: this.headers,
                });

                const result = response.data;

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
                const response = await this.httpClient.get(`/_action/wg-dunning/${dunningId}/pdf`, {
                    headers: {
                        Authorization: `Bearer ${Shopware.Context.api.authToken.access}`,
                    },
                    responseType: 'blob',
                });

                const url = window.URL.createObjectURL(response.data);
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
