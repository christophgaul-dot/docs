import template from './sw-cms-el-wg-faq.html.twig';

const { Component, Mixin } = Shopware;

Component.register('sw-cms-el-wg-faq', {
    template,
    mixins: [Mixin.getByName('cms-element')],

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('wg-faq');
        },
    },
});
