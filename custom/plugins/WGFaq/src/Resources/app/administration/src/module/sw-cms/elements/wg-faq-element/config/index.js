import template from './sw-cms-el-config-wg-faq.html.twig';

const { Component, Mixin } = Shopware;

Component.register('sw-cms-el-config-wg-faq', {
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
