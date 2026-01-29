(function () {
    farmOS.map.behaviors.rcd_property_zoom = {
        attach: function (instance) {
            // Only attach to maps for editing land use areas.
            if (instance.target.id.includes("farm-map-rcd")) {
                // Create a layer for the property location.
                const opts = {
                    title: 'Property',
                    color: 'blue',
                };
                instance.propertyLocationLayer = instance.addLayer('vector', opts);

                // If property geometry exists, add it to the layer.
                if (instance.farmMapSettings.behaviors.rcd_property_zoom.property_geometry) {
                    this.updatePropertyGeometry(instance, instance.farmMapSettings.behaviors.rcd_property_zoom.property_geometry);
                }
            }
        },

        // When updating property geometry, update the current location layer.
        updatePropertyGeometry: function (instance, wkt) {

            // Clear features from the layer.
            instance.propertyLocationLayer.getSource().clear();

            // If WKT is not empty, add features to the layer and zoom.
            if (wkt) {
                instance.propertyLocationLayer.getSource().addFeatures(instance.readFeatures('wkt', wkt));
                instance.zoomToLayer(instance.propertyLocationLayer);
            }
        },

        // Make sure this runs before farmOS.map.behaviors.wkt.
        weight: 10,
    };
})();
