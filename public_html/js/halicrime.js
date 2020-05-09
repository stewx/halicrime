var map;
(function () {
  var app = angular.module("halicrime", []);

  /**
   * Handle map-related parts of the app
   */
  app.controller("MapController", [
    "$scope",
    "$http",
    function ($scope, $http) {
      var MARKER_LIMIT = 1000;
      //var map;
      var markers = [];
      var ids_mapped = [];
      var open_info_windows = [];

      var vm = this;
      vm.radius = 200;

      function toTitleCase(str) {
        return str.replace(/\w\S*/g, function (txt) {
          return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();
        });
      }
      function cleanupMarkers() {
        if (markers.length > MARKER_LIMIT) {
          // Remove markers not in current view
          var countRemoved = 0;
          for (var i = 0; i < markers.length; i++) {
            if (markers.length <= MARKER_LIMIT) {
              console.log(
                "Number of markers is within the limit (" +
                  markers.length +
                  "/" +
                  MARKER_LIMIT +
                  ")."
              );
              break;
            }

            if (!map.getBounds().contains(markers[i].getPosition())) {
              // Delete from marker list
              markers[i].setMap(null);
              markers.splice(i, 1);
              countRemoved++;
            }
          }
          console.log(
            "Removed " + countRemoved + " markers that were not in bounds."
          );
        }
      }

      function handleMapClick(event) {
        vm.region.setOptions({
          center: event.latLng,
        });
      }

      function truncate(degree) {
        var decimals = 3;
        var div = Math.pow(10, decimals);
        return Math.ceil(degree * div) / div;
      }

      function handleBoundsChange(event) {
        var bounds = map.getBounds();
        var northeastCoords = bounds.getNorthEast();
        var southwestCoords = bounds.getSouthWest();
        // could likely cache requests easier with truncated coords
        var swLng = truncate(southwestCoords.lng());
        var swLat = truncate(southwestCoords.lat());
        var neLng = truncate(northeastCoords.lng());
        var neLat = truncate(northeastCoords.lat());

        $http({
          url: "ajax.php",
          method: "GET",
          params: {
            action: "get_events",
            bounds: [swLng, swLat, neLng, neLat].join(","),
          },
          timeout: 10000, // 10 second timeout
        }).then(function successCallback(response) {
          var events = response.data.events || [];
          console.log("Got " + events.length + " events from the API.");

          // Add markers
          addMarkers(events);

          // Clean up old markers if needed
          cleanupMarkers();
        });
      }

      function getIcon(crime_type) {
        var icon_path = "img/crime.png";
        switch (crime_type) {
          case "ASSAULT":
            icon_path = "img/assault.png";
            break;
          case "THEFT OF VEHICLE":
            icon_path = "img/theftofmotorvehicle.png";
            break;
          case "BREAK AND ENTER":
            icon_path = "img/breakandenter.png";
            break;
          case "THEFT FROM VEHICLE":
            icon_path = "img/theftfrommotorvehicle.png";
            break;
          case "ROBBERY":
            icon_path = "img/robbery.png";
            break;
        }
        return icon_path;
      }

      function addMarkers(events) {
        for (var i = 0; i < events.length; i++) {
          var event = events[i];
          if (ids_mapped.indexOf(event.id) === -1) {
            var marker = new google.maps.Marker({
              map: map,
              position: {
                lat: parseFloat(event.latitude),
                lng: parseFloat(event.longitude),
              },
              title: event.event_type,
              icon: getIcon(event.event_type),
              animation: google.maps.Animation.DROP,
            });
            markers.push(marker);
            var contentString =
              "<h4>" +
              toTitleCase(event.event_type) +
              "</h4>" +
              "<p>" +
              toTitleCase(event.street_name) +
              "</p>" +
              "<p>" +
              event.date +
              "</p>";

            var infoWindow = new google.maps.InfoWindow();
            bindInfoWindow(marker, map, infoWindow, contentString);

            ids_mapped.push(event.id);
          }
        }
      }

      function bindInfoWindow(marker, map_object, infowindow, html) {
        google.maps.event.addListener(marker, "click", function () {
          for (var i = 0; i < open_info_windows.length; i++) {
            open_info_windows[i].close();
          }
          open_info_windows = [];
          infowindow.setContent(html);
          infowindow.open(map_object, marker);
          open_info_windows.push(infowindow);
        });
      }

      vm.setRadius = function (radius) {
        if (vm.region) {
          vm.region.setOptions({ radius: parseInt(radius, 10) });
        }
      };

      /**
        Initialize map settings 
        **/
      (function () {
        var mapOptions = {
          center: {
            lat: 44.6516904,
            lng: -63.5839593,
          },
          zoom: 15,
        };
        map = new google.maps.Map(
          document.getElementById("map-canvas"),
          mapOptions
        );
        vm.region = new google.maps.Circle({
          strokeColor: "#FF0000",
          strokeOpacity: 0.8,
          strokeWeight: 2,
          fillColor: "#FF0000",
          fillOpacity: 0.35,
          map: map,
          center: mapOptions.center,
          draggable: true,
          radius: vm.radius,
          zIndex: 50,
        });

        map.addListener("click", handleMapClick);
        map.addListener("bounds_changed", _.debounce(handleBoundsChange, 500));
      })();
    },
  ]);

  /**
   * Handle form-related aspects of the app
   */
  app.controller("SubscriptionController", [
    "$scope",
    "$http",
    function ($scope, $http) {
      var vm = this;
      var stage = "";

      vm.form = {};

      vm.isStage = function (stage_name) {
        return stage === stage_name;
      };

      vm.setStage = function (stage_name) {
        stage = stage_name;
      };

      function validEmail(email) {
        var re = /^([\w-]+(?:\.[\w-]+)*)@((?:[\w-]+\.)*\w[\w-]{0,66})\.([a-z]{2,6}(?:\.[a-z]{2})?)$/i;
        return re.test(email);
      }

      vm.subscribe = function (region) {
        var center = region.getCenter();
        $http({
          method: "POST",
          url: "ajax.php",
          data: {
            action: "subscribe",
            email: vm.form.email,
            center: { lat: center.lat(), lng: center.lng() },
            radius: region.getRadius(),
          },
        }).then(
          function successCallback(response) {
            vm.setStage("confirm");
          },
          function errorCallback(response) {
            vm.setStage("subscribe_error");
          }
        );
      };
    },
  ]);
})();
