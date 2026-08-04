<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('wizardNextButton', () => ({
            isUploading: false,

            get uploadingClass() {
                return this.isUploading ? 'opacity-70 cursor-wait' : '';
            },

            init() {
                const form = this.$el.closest('form');

                form?.addEventListener('form-processing-started', () => {
                    this.isUploading = true;
                });

                form?.addEventListener('form-processing-finished', () => {
                    this.isUploading = false;
                });
            },
        }));
    });
</script>
