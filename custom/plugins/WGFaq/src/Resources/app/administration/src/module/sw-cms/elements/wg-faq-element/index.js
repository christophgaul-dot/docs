import './component';
import './config';
import './preview';

Shopware.Service('cmsService').registerCmsElement({
    name: 'wg-faq',
    label: 'wg-faq.cms.elementLabel',
    component: 'sw-cms-el-wg-faq',
    configComponent: 'sw-cms-el-config-wg-faq',
    previewComponent: 'sw-cms-el-preview-wg-faq',
    defaultConfig: {
        title: {
            source: 'static',
            value: 'Häufig gestellte Fragen',
        },
        displayFilter: {
            source: 'static',
            value: 'showOnFaqPage',
        },
        showDetailLinks: {
            source: 'static',
            value: false,
        },
    },
});
