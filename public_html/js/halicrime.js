
(function () {
    
    var app = angular.module('halicrime', []);
    
    /**
     * Handle map-related parts of the app
     */
    app.controller('MapController', ['$scope', '$http', function ($scope, $http) {        
        var GOOGLE_MAPS_API_KEY = 'AIzaSyCpEmKC7qoSMNi5k21FayydQ19xW3SbzOg';
        var MARKER_LIMIT = 1000;
        var map;
        var markers = [];
        var ids_mapped = [];
        var open_info_windows = [];
        var stage;
        
        var vm = this;
        vm.radius = 200;
      
        function handleMapClick(event) {
            vm.region.setOptions({
              center: event.latLng
            });
        }
        
        vm.setRadius = function(radius) {
            if (vm.region) {
                vm.region.setOptions({radius: parseInt(radius, 10)});                
            }
        };
        
        /**
        Initialize map settings 
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
            vm.region = new google.maps.Circle({
              strokeColor: '#FF0000',
              strokeOpacity: 0.8,
              strokeWeight: 2,
              fillColor: '#FF0000',
              fillOpacity: 0.35,
              map: map,
              center: mapOptions.center,
              draggable: true,
              radius: vm.radius
            });
            
            map.addListener('click', handleMapClick);
        })();


    }]);
    
    /**
     * Handle form-related aspects of the app
    */
    app.controller('SubscriptionController', ['$scope', '$http', function ($scope, $http) {
        var vm = this;
        var stage = '';
        
        vm.form = {};
        
        vm.isStage = function(stage_name) {
            return stage === stage_name;
        };
        
        vm.setStage = function(stage_name) {
            stage = stage_name;
        };
              
        function validEmail(email) {
            var re = /^([\w-]+(?:\.[\w-]+)*)@((?:[\w-]+\.)*\w[\w-]{0,66})\.([a-z]{2,6}(?:\.[a-z]{2})?)$/i;
            return re.test(email);
        }
        
        // $scope.validateForm = function(form) {
            // if (validEmail(form.email)) {
                // return true;
            // } else {
                // $scope.setStage('invalid_email');
                // return false;
            // }
        // };
      
        vm.subscribe = function(region) {
            var center = region.getCenter();
            $http({
              method: 'POST',
              url: 'ajax.py',
              data: {
                  email: vm.form.email,
                  center: {lat: center.lat(),
                           lng: center.lng()},
                  radius: region.getRadius()
              }
            }).then(function successCallback(response) {
                vm.setStage('subscribed');

                
              }, function errorCallback(response) {
                vm.setStage('subscribe_error');
                
              });
            
            
        };
        
        
        
    }]);


})();