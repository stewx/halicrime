(function () {
    
    var app = angular.module('halicrime', []);
    
    app.controller('MapController', ['$scope', '$http', function ($scope, $http) {        
        var GOOGLE_MAPS_API_KEY = 'AIzaSyCpEmKC7qoSMNi5k21FayydQ19xW3SbzOg';
        var MARKER_LIMIT = 1000;
        var map;
        var markers = [];
        var ids_mapped = [];
        var open_info_windows = [];
        var region_circle;
        $scope.radius = 50;
      
        function handleMapClick(event) {
            var coordinates = event.latLng;
            
            region_circle.setOptions({
              center: coordinates
            });
        }
        
        $scope.setRadius = function() {
            if (region_circle) {
                region_circle.setOptions({radius: parseInt($scope.radius, 10)});                
            }
        };
      
        /**
        Initialize game settings 
        **/
		(function () {
          
            var mapOptions = {
                center: {
                    lat: 44.6516904,
                    lng: -63.5839593
                },
                zoom: 15
            };
            map = new google.maps.Map(document.getElementById('map-canvas'), mapOptions);
            region_circle = new google.maps.Circle({
              strokeColor: '#FF0000',
              strokeOpacity: 0.8,
              strokeWeight: 2,
              fillColor: '#FF0000',
              fillOpacity: 0.35,
              map: map,
              center: mapOptions.center,
              draggable: true,
              radius: 500
            });
            
            map.addListener('click', handleMapClick);
        })();


    }]);


})();