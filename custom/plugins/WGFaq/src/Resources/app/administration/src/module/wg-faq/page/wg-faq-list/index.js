import template from './wg-faq-list.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('wg-faq-list', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            faqs: null,
            isLoading: true,
            sortBy: 'position',
            sortDirection: 'ASC',
            isExporting: false,
            isImporting: false,
            showImportModal: false,
            importFile: null,
            importResult: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        faqRepository() {
            return this.repositoryFactory.create('wg_faq');
        },

        faqColumns() {
            return [
                {
                    property: 'question',
                    dataIndex: 'question',
                    label: this.$tc('wg-faq.list.columnQuestion'),
                    routerLink: 'wg.faq.detail',
                    allowResize: true,
                    primary: true,
                },
                {
                    property: 'active',
                    label: this.$tc('wg-faq.list.columnActive'),
                    allowResize: true,
                    width: '100px',
                },
                {
                    property: 'position',
                    label: this.$tc('wg-faq.list.columnPosition'),
                    allowResize: true,
                    width: '100px',
                },
                {
                    property: 'showOn',
                    label: this.$tc('wg-faq.list.columnShowOn'),
                    allowResize: true,
                    sortable: false,
                },
            ];
        },
    },

    methods: {
        async getList() {
            this.isLoading = true;

            const criteria = new Criteria(this.page, this.limit);
            criteria.setTerm(this.term);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));

            try {
                const result = await this.faqRepository.search(criteria);
                this.faqs = result;
                this.total = result.total;
            } catch (error) {
                this.createNotificationError({
                    message: error.message,
                });
            } finally {
                this.isLoading = false;
            }
        },

        getShowOnLabels(faq) {
            const labels = [];
            if (faq.showOnHome) labels.push(this.$tc('wg-faq.list.home'));
            if (faq.showOnCategory) labels.push(this.$tc('wg-faq.list.category'));
            if (faq.showOnProduct) labels.push(this.$tc('wg-faq.list.product'));
            if (faq.showOnFaqPage) labels.push(this.$tc('wg-faq.list.faqPage'));
            return labels.join(', ');
        },

        async onDeleteFaq(id) {
            try {
                await this.faqRepository.delete(id);
                this.createNotificationSuccess({
                    message: this.$tc('wg-faq.list.messageDeleteSuccess'),
                });
                await this.getList();
            } catch (error) {
                this.createNotificationError({
                    message: error.message,
                });
            }
        },

        async onExport() {
            this.isExporting = true;

            try {
                const httpClient = Shopware.Application.getContainer('init').httpClient;
                const response = await httpClient.get('/wg-faq/export', {
                    headers: {
                        Accept: 'application/json',
                        Authorization: 'Bearer ' + Shopware.Context.api.authToken.access,
                    },
                    responseType: 'blob',
                });

                const blob = response.data;

                const url = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                const today = new Date().toISOString().slice(0, 10);
                link.href = url;
                link.download = `wg-faq-export-${today}.json`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                window.URL.revokeObjectURL(url);

                this.createNotificationSuccess({
                    message: this.$tc('wg-faq.list.exportSuccess'),
                });
            } catch (error) {
                this.createNotificationError({
                    message: this.$tc('wg-faq.list.exportError') + ': ' + error.message,
                });
            } finally {
                this.isExporting = false;
            }
        },

        onOpenImport() {
            this.showImportModal = true;
            this.importFile = null;
            this.importResult = null;
        },

        onImportFileSelected(file) {
            this.importFile = file;
            this.importResult = null;
        },

        async onImport() {
            if (!this.importFile) {
                return;
            }

            this.isImporting = true;
            this.importResult = null;

            try {
                const fileContent = await this.readFileContent(this.importFile);
                const parsed = JSON.parse(fileContent);

                if (!parsed.faqs || !Array.isArray(parsed.faqs)) {
                    throw new Error('Ungültiges Format: "faqs" Array fehlt.');
                }

                const httpClient = Shopware.Application.getContainer('init').httpClient;
                const response = await httpClient.post('/wg-faq/import', parsed, {
                    headers: {
                        'Content-Type': 'application/json',
                        Authorization: 'Bearer ' + Shopware.Context.api.authToken.access,
                    },
                });

                this.importResult = response.data;

                if (this.importResult.success) {
                    this.createNotificationSuccess({
                        message: this.importResult.message,
                    });
                    await this.getList();
                } else {
                    this.createNotificationWarning({
                        message: this.importResult.message,
                    });
                }
            } catch (error) {
                this.importResult = {
                    success: false,
                    message: error.message,
                    errors: [],
                };
                this.createNotificationError({
                    message: this.$tc('wg-faq.list.importError') + ': ' + error.message,
                });
            } finally {
                this.isImporting = false;
            }
        },

        readFileContent(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = (e) => resolve(e.target.result);
                reader.onerror = () => reject(new Error('Datei konnte nicht gelesen werden.'));
                reader.readAsText(file);
            });
        },
    },
});
