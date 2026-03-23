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
    },
});
