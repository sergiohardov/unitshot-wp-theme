import { setOptions, importLibrary } from '@googlemaps/js-api-loader';

export default function ContactPage() {
    Alpine.data('ContactPage', () => ({
        async init() {
            const mapContainer = this.$el.querySelector('.us-page-contact__map')

            const apikey    = mapContainer.getAttribute('data-map-api')
            const lat       = mapContainer.getAttribute('data-map-lat')
            const lng       = mapContainer.getAttribute('data-map-lng')
            const zoom      = mapContainer.getAttribute('data-map-zoom')

            setOptions({ key: apikey });

            const { Map }                   = await importLibrary("maps");
            const { AdvancedMarkerElement } = await importLibrary("marker");

            const position = { lat: +lat, lng: +lng };

            const map = new Map(mapContainer, {
                center: position,
                zoom: +zoom,
                mapId: 'default',
            });

            new AdvancedMarkerElement({ map, position });
        }
    }))
}
