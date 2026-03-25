import template from './wg-faq-detail.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

Component.register('wg-faq-detail', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            faq: null,
            isLoading: false,
            isSaving: false,
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

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        ...mapPropertyErrors('faq', ['question', 'answer']),
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.isLoading = true;
            try {
                const criteria = new Criteria();
                criteria.addAssociation('media');
                this.faq = await this.faqRepository.get(this.$route.params.id, Shopware.Context.api, criteria);
            } catch (error) {
                this.createNotificationError({
                    message: error.message,
                });
            } finally {
                this.isLoading = false;
            }
        },

        async onSave() {
            this.isSaving = true;
            try {
                await this.faqRepository.save(this.faq);
                this.createNotificationSuccess({
                    message: this.$tc('wg-faq.detail.messageSaveSuccess'),
                });
                await this.createdComponent();
            } catch (error) {
                this.createNotificationError({
                    message: this.$tc('wg-faq.detail.messageSaveError'),
                });
            } finally {
                this.isSaving = false;
            }
        },

        onSetMediaItem({ targetId }) {
            this.faq.mediaId = targetId;
        },

        onRemoveMediaItem() {
            this.faq.mediaId = null;
            this.faq.media = null;
        },

        onMediaDropped(mediaItem) {
            this.onSetMediaItem({ targetId: mediaItem.id });
        },

    },
});
