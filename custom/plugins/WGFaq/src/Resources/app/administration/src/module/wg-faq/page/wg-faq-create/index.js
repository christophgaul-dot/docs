const { Component } = Shopware;

Component.extend('wg-faq-create', 'wg-faq-detail', {
    methods: {
        async createdComponent() {
            this.faq = this.faqRepository.create();
            this.faq.active = true;
            this.faq.position = 0;
            this.faq.showOnHome = false;
            this.faq.showOnCategory = false;
            this.faq.showOnProduct = false;
            this.faq.showOnFaqPage = true;
            this.isLoading = false;
        },

        async onSave() {
            this.isSaving = true;
            try {
                await this.faqRepository.save(this.faq);
                this.createNotificationSuccess({
                    message: this.$tc('wg-faq.detail.messageSaveSuccess'),
                });
                this.$router.push({ name: 'wg.faq.detail', params: { id: this.faq.id } });
            } catch (error) {
                this.createNotificationError({
                    message: this.$tc('wg-faq.detail.messageSaveError'),
                });
            } finally {
                this.isSaving = false;
            }
        },
    },
});
