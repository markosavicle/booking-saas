const DRAG_THRESHOLD_PX = 5;

/**
 * x-drag-scroll: lets mouse users grab-and-drag a horizontal scroller. Touch and pen keep
 * native scrolling. A drag past the threshold swallows the click that ends it, so releasing
 * over a chip doesn't select it; `data-dragging` pauses scroll-snap while the pointer is down.
 */
export default function dragScroll(el, _directive, { cleanup }) {
    let origin = null;
    let dragged = false;

    const onPointerDown = (event) => {
        if (event.pointerType !== 'mouse' || event.button !== 0) return;

        origin = { x: event.clientX, scrollLeft: el.scrollLeft, pointerId: event.pointerId };
        dragged = false;
    };

    const onPointerMove = (event) => {
        if (!origin) return;

        const deltaX = event.clientX - origin.x;

        if (!dragged) {
            if (Math.abs(deltaX) < DRAG_THRESHOLD_PX) return;

            dragged = true;
            el.dataset.dragging = '';
            el.setPointerCapture(origin.pointerId);
        }

        el.scrollLeft = origin.scrollLeft - deltaX;
    };

    const onPointerUp = () => {
        origin = null;
        // Removing it re-enables snapping, which settles on the nearest chip.
        delete el.dataset.dragging;
    };

    const onClick = (event) => {
        if (!dragged) return;

        event.preventDefault();
        event.stopPropagation();
        dragged = false;
    };

    const listeners = [
        ['pointerdown', onPointerDown],
        ['pointermove', onPointerMove],
        ['pointerup', onPointerUp],
        ['pointercancel', onPointerUp],
        ['click', onClick, true],
    ];

    listeners.forEach(([type, listener, capture]) => el.addEventListener(type, listener, capture));
    cleanup(() => listeners.forEach(([type, listener, capture]) => el.removeEventListener(type, listener, capture)));
}
