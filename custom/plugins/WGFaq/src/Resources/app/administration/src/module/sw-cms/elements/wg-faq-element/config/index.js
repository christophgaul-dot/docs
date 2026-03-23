import template from './sw-cms-el-config-wg-faq.html.twig';

const { Component, Mixin } = Shopware;

Component.register('sw-cms-el-config-wg-faq', {
    template,
    mixins: [Mixin.getByName('cms-element')],

    computed: {
        headlineTagOptions() {
            return [
                { value: 'h2', label: 'H2' },
                { value: 'h3', label: 'H3' },
                { value: 'h4', label: 'H4' },
                { value: 'h5', label: 'H5' },
                { value: 'h6', label: 'H6' },
                { value: 'p', label: 'Paragraph (p)' },
            ];
        },

        displayFilterOptions() {
            return [
                { value: 'showOnFaqPage', label: this.$tc('wg-faq.list.faqPage') },
                { value: 'showOnHome', label: this.$tc('wg-faq.list.home') },
                { value: 'showOnCategory', label: this.$tc('wg-faq.list.category') },
                { value: 'showOnProduct', label: this.$tc('wg-faq.list.product') },
            ];
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('wg-faq');
        },
    },
});
