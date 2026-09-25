import Alpine from 'alpinejs';
import bookingWidget from './booking';

Alpine.data('bookingWidget', bookingWidget);

window.Alpine = Alpine;
Alpine.start();
