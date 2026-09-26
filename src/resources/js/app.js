import Alpine from 'alpinejs';
import bookingWidget from './booking';
import dragScroll from './drag-scroll';

Alpine.data('bookingWidget', bookingWidget);
Alpine.directive('drag-scroll', dragScroll);

window.Alpine = Alpine;
Alpine.start();
