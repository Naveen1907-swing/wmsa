<?php
// In a real-world scenario, store this in a secure configuration file
$apiKey = 'AIzaSyD5I6FJs6pkKEyHx-oq6-s2w4CdV-yNObI';

// Initialize variables
$searchQuery = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
    $searchQuery = $_POST['search'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professional Map Explorer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        .map-container {
            height: calc(100vh - 4rem);
        }
        #map, #street-view {
            height: 100%;
            width: 100%;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <nav class="bg-blue-600 text-white p-4">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold">Professional Map Explorer</h1>
            <form id="search-form" class="flex-grow max-w-2xl mx-4">
                <div class="relative">
                    <input type="text" name="search" id="search-input" placeholder="Search for a place" required
                           class="w-full p-2 pl-10 pr-4 rounded-full text-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-300"
                           value="<?php echo htmlspecialchars($searchQuery); ?>">
                    <button type="submit" class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
            <button id="toggle-view" class="bg-blue-700 hover:bg-blue-800 text-white font-bold py-2 px-4 rounded-full">
                <i class="fas fa-exchange-alt mr-2"></i>Toggle View
            </button>
        </div>
    </nav>

    <div class="container mx-auto p-4">
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="map-container grid grid-cols-1 md:grid-cols-2">
                <div id="map" class="col-span-1"></div>
                <div id="street-view" class="col-span-1"></div>
            </div>
        </div>
    </div>

    <script>
        let map;
        let panorama;
        let searchBox;
        let markers = [];

        function initMap() {
            const defaultLocation = { lat: 40.7128, lng: -74.0060 };

            map = new google.maps.Map(document.getElementById("map"), {
                center: defaultLocation,
                zoom: 14,
                styles: [
                    {
                        "featureType": "all",
                        "elementType": "geometry.fill",
                        "stylers": [{"weight": "2.00"}]
                    },
                    {
                        "featureType": "all",
                        "elementType": "geometry.stroke",
                        "stylers": [{"color": "#9c9c9c"}]
                    },
                    {
                        "featureType": "all",
                        "elementType": "labels.text",
                        "stylers": [{"visibility": "on"}]
                    },
                    {
                        "featureType": "landscape",
                        "elementType": "all",
                        "stylers": [{"color": "#f2f2f2"}]
                    },
                    {
                        "featureType": "landscape",
                        "elementType": "geometry.fill",
                        "stylers": [{"color": "#ffffff"}]
                    },
                    {
                        "featureType": "landscape.man_made",
                        "elementType": "geometry.fill",
                        "stylers": [{"color": "#ffffff"}]
                    },
                    {
                        "featureType": "poi",
                        "elementType": "all",
                        "stylers": [{"visibility": "off"}]
                    },
                    {
                        "featureType": "road",
                        "elementType": "all",
                        "stylers": [{"saturation": -100}, {"lightness": 45}]
                    },
                    {
                        "featureType": "road",
                        "elementType": "geometry.fill",
                        "stylers": [{"color": "#eeeeee"}]
                    },
                    {
                        "featureType": "road",
                        "elementType": "labels.text.fill",
                        "stylers": [{"color": "#7b7b7b"}]
                    },
                    {
                        "featureType": "road",
                        "elementType": "labels.text.stroke",
                        "stylers": [{"color": "#ffffff"}]
                    },
                    {
                        "featureType": "road.highway",
                        "elementType": "all",
                        "stylers": [{"visibility": "simplified"}]
                    },
                    {
                        "featureType": "road.arterial",
                        "elementType": "labels.icon",
                        "stylers": [{"visibility": "off"}]
                    },
                    {
                        "featureType": "transit",
                        "elementType": "all",
                        "stylers": [{"visibility": "off"}]
                    },
                    {
                        "featureType": "water",
                        "elementType": "all",
                        "stylers": [{"color": "#46bcec"}, {"visibility": "on"}]
                    },
                    {
                        "featureType": "water",
                        "elementType": "geometry.fill",
                        "stylers": [{"color": "#c8d7d4"}]
                    },
                    {
                        "featureType": "water",
                        "elementType": "labels.text.fill",
                        "stylers": [{"color": "#070707"}]
                    },
                    {
                        "featureType": "water",
                        "elementType": "labels.text.stroke",
                        "stylers": [{"color": "#ffffff"}]
                    }
                ]
            });

            panorama = new google.maps.StreetViewPanorama(
                document.getElementById("street-view"),
                {
                    position: defaultLocation,
                    pov: { heading: 165, pitch: 0 },
                    zoom: 1,
                }
            );

            map.setStreetView(panorama);

            const input = document.getElementById("search-input");
            searchBox = new google.maps.places.SearchBox(input);

            map.addListener("bounds_changed", () => {
                searchBox.setBounds(map.getBounds());
            });

            searchBox.addListener("places_changed", () => {
                const places = searchBox.getPlaces();

                if (places.length == 0) {
                    return;
                }

                markers.forEach((marker) => {
                    marker.setMap(null);
                });
                markers = [];

                const bounds = new google.maps.LatLngBounds();

                places.forEach((place) => {
                    if (!place.geometry || !place.geometry.location) {
                        console.log("Returned place contains no geometry");
                        return;
                    }

                    const marker = new google.maps.Marker({
                        map,
                        title: place.name,
                        position: place.geometry.location,
                    });

                    markers.push(marker);

                    if (place.geometry.viewport) {
                        bounds.union(place.geometry.viewport);
                    } else {
                        bounds.extend(place.geometry.location);
                    }

                    panorama.setPosition(place.geometry.location);
                });

                map.fitBounds(bounds);
            });

            document.getElementById('search-form').addEventListener('submit', function(e) {
                e.preventDefault();
                const places = searchBox.getPlaces();
                if (places && places.length > 0) {
                    google.maps.event.trigger(searchBox, 'places_changed');
                }
            });

            document.getElementById('toggle-view').addEventListener('click', function() {
                const mapContainer = document.getElementById('map').parentElement;
                const streetViewContainer = document.getElementById('street-view').parentElement;
                mapContainer.classList.toggle('md:col-span-2');
                streetViewContainer.classList.toggle('md:col-span-2');
                streetViewContainer.classList.toggle('md:hidden');
            });

            <?php if (!empty($searchQuery)): ?>
            setTimeout(() => {
                input.value = <?php echo json_encode($searchQuery); ?>;
                const event = new Event('places_changed');
                searchBox.dispatchEvent(event);
            }, 1000);
            <?php endif; ?>
        }
    </script>
    <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo $apiKey; ?>&libraries=places&callback=initMap" async defer></script>
</body>
</html>
