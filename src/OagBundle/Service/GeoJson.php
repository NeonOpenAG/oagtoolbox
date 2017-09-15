<?php

namespace OagBundle\Service;

use OagBundle\Entity\Geolocation;

class GeoJson extends AbstractService {

    /**
     * Get a GeoJson feature as an associative array from one of our Geolocation
     * entities.
     *
     * @param Geolocation $Geoloc
     * @return array
     */
    public function featureFromGeoloc(Geolocation $geoloc) {
        return array(
            'id' => $geoloc->getLocationIdCode(), // TODO take into account vocab
            'type' => 'Feature',
            'geometry' => array(
                'type' => 'Point',
                'coordinates' => array(
                    $geoloc->getPointPosLong(),
                    $geoloc->getPointPosLat()
                )
            ),
            'properties' => array(
                'title' => $geoloc->getName(),
                'nid' => $geoloc->getLocationIdCode() // TODO take into account vocab
            )
        );
    }

    /**
     * Get a Geojson set of data fit for NeonMap from a series of Geolocations.
     *
     * @param Geolocation[] $geolocs
     * @return array
     */
    public function getGeojson($geolocs) {
        return array(
            'type' => 'FeatureCollection',
            'features' => array_map(array($this, 'featureFromGeoloc'), $geolocs)
        );
    }

}
