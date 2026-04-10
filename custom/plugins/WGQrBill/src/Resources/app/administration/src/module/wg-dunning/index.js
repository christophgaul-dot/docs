import './page/wg-dunning-list';
import deDE from './snippet/de-DE/de-DE.json';
import enGB from './snippet/en-GB/en-GB.json';

const { Module } = Shopware;

Module.register('wg-dunning', {
    type: 'plugin',
    name: 'wg-dunning',
    title: 'wg-dunning.general.title',
    description: 'wg-dunning.general.description',
    color: '#e74c3c',
    icon: 'regular-bell',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB,
    },

    routes: {
        list: {
            component: 'wg-dunning-list',
            path: 'list',
        },
    },

    navigation: [{
        label: 'wg-dunning.general.title',
        color: '#e74c3c',
        path: 'wg.dunning.list',
        icon: 'regular-bell',
        parent: 'sw-order',
        position: 100,
    }],
});
