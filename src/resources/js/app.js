import Alpine from 'alpinejs';
import bookingWidget from './booking';
import dragScroll from './drag-scroll';
import gallery from './gallery';

Alpine.data('bookingWidget', bookingWidget);
Alpine.data('gallery', gallery);
Alpine.directive('drag-scroll', dragScroll);

window.Alpine = Alpine;
Alpine.start();
