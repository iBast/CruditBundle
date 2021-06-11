'use strict';
import L from 'leaflet';
require('leaflet-easybutton');
require('@ansur/leaflet-pulse-icon');
require('leaflet-ajax/dist/leaflet.ajax.min');

window.addEventListener('load', function () {

  document.querySelectorAll(".crudit-map").forEach(map_elem => {
    let center = [map_elem.dataset.lat, map_elem.dataset.lng]
    let map = L.map(map_elem.id, {center: center, zoom: map_elem.dataset.zoom});

    var marker = L.marker(center).addTo(map);

    let osm = L.tileLayer('https://{s}.tile.osm.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://osm.org/copyright">OpenStreetMap</a> contributors'
    });
    let googleSat = L.tileLayer('http://{s}.google.com/vt/lyrs=s&x={x}&y={y}&z={z}', {
      maxZoom: 20,
      subdomains: ['mt0', 'mt1', 'mt2', 'mt3']
    });
    osm.addTo(map);
    var baseMaps = {
      "OpenStreetMap": osm,
      "Satellite": googleSat
    }
    var overlay = {
    }
    L.control.layers(baseMaps, overlay).addTo(map);
    //sites_layer_poly.addTo(this.map);
    //sites_layer_point.addTo(this.map);
    //point_layer.addTo(this.map);

    /*
    // couche geojson
    let sites_layer_poly = new L.GeoJSON.AJAX("/api/site_collecte/poly.json", {
      onEachFeature(feature, layer) {
        layer.bindPopup(
            "<iframe src=\"/api/site_collecte/" + feature.id + "/popup\"></iframe>"
        ).openPopup();
      }
    });
    let sites_layer_point = new L.GeoJSON.AJAX("/api/site_collecte/points.json");

    var greenIcon = L.icon({
      iconUrl: '/img/icons/tree_outline.svg',
      iconSize: [32, 32] // size of the icon
    });

    let point_layer = new L.GeoJSON.AJAX("/api/point_collecte/points.json", {
      pointToLayer: function (geoJsonPoint, latlng) {
        return L.marker(latlng, {icon: greenIcon}).bindPopup(
            "<iframe src=\"/api/point_collecte/" + geoJsonPoint.id + "/popup\"></iframe>"
        ).openPopup();
      }
    });

    let commune_layer;
    if (communeId === undefined) {
      commune_layer = new L.GeoJSON.AJAX("/api/communes/poly.json");
    } else {
      commune_layer = new L.GeoJSON.AJAX("/api/communes/" + communeId);
    }
    */

  });

});
