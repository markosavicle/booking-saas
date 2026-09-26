/**
 * Lightbox for the shop gallery. The native <dialog> gives us Esc to close,
 * focus trapping and focus return for free; this only tracks which photo is open.
 */
export default (images) => ({
    images,
    index: null,

    get current() {
        return this.index === null ? null : this.images[this.index];
    },

    show(index) {
        this.index = index;
        this.$refs.dialog.showModal();
    },

    step(delta) {
        if (this.index !== null) {
            this.index = (this.index + delta + this.images.length) % this.images.length;
        }
    },

    close() {
        this.$refs.dialog.close();
    },
});
