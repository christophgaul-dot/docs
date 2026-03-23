import './page/wg-faq-list';
import './page/wg-faq-detail';
import './page/wg-faq-create';
import deDE from './snippet/de-DE/de-DE.json';
import enGB from './snippet/en-GB/en-GB.json';

const { Module } = Shopware;

Module.register('wg-faq', {
    type: 'plugin',
    name: 'wg-faq',
    title: 'wg-faq.general.mainMenuItemGeneral',
    description: 'wg-faq.general.descriptionTextModule',
    color: '#ff6b35',
    icon: 'regular-question-circle',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB,
    },

    routes: {
        list: {
            component: 'wg-faq-list',
            path: 'list',
        },
        detail: {
            component: 'wg-faq-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'wg.faq.list',
            },
        },
        create: {
            component: 'wg-faq-create',
            path: 'create',
            meta: {
                parentPath: 'wg.faq.list',
            },
        },
    },

    navigation: [{
        label: 'wg-faq.general.mainMenuItemGeneral',
        color: '#ff6b35',
        path: 'wg.faq.list',
        icon: 'regular-question-circle',
        parent: 'sw-catalogue',
        position: 100,
    }],
});
