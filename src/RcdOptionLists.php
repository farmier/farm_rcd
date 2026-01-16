<?php

declare(strict_types=1);

namespace Drupal\farm_rcd;

/**
 * Define option lists used by this module.
 *
 * @internal
 */
class RcdOptionLists {

  /**
   * Stakeholder types.
   *
   * @return array
   *   Returns an array of allowed value options.
   */
  public static function stakeholderTypes() {
    return [
      'manager' => t('Manager'),
      'municipality' => t('Municipality'),
      'landowner' => t('Landowner'),
      'lessee' => t('Lessee'),
    ];
  }

  /**
   * Stakeholder groups.
   *
   * @return array
   *   Returns an array of allowed value options.
   */
  public static function stakeholderGroups() {
    return [
      'beginning' => t('Beginning farmer or rancher (less than 10 years)'),
      'family' => t('Family farm'),
      'female' => t('Female'),
      'lgbtq' => t('LGBTQ+'),
      'veteran' => t('Veteran'),
      'black' => t('Black or African American'),
      'native' => t('American Indian or Alaska Native'),
      'hispanic' => t('Hispanic or Latino'),
      'asian' => t('Asian'),
      'pacific' => t('Pacific Islander'),
      'optout' => t('Prefer not to answer'),
      'other' => t('Other'),
    ];
  }

  /**
   * Land uses.
   *
   * @return array
   *   Returns an array of allowed value options.
   */
  public static function landUses() {
    return [
      'grazing' => t('Grazing'),
      'vineyards' => t('Vineyards'),
      'orchards' => t('Orchards'),
      'rowcrops' => t('Row crops'),
      'natural' => t('Natural lands'),
      'other' => t('Other'),
    ];
  }

  /**
   * Land types.
   *
   * @return array
   *   Returns an array of allowed value options.
   */
  public static function landTypes() {
    return [
      'rcd_row_crops' => t('Row crops'),
      'rcd_orchard' => t('Orchard'),
      'rcd_vineyard' => t('Vineyard'),
      'rcd_grazing' => t('Grazing'),
      'rcd_pastureland' => t('Pastureland'),
      'rcd_natural_area' => t('Natural area'),
      'rcd_riparian_area' => t('Riparian area'),
      'other' => t('Other'),
    ];
  }

  /**
   * Goals.
   *
   * @return array
   *   Returns an array of allowed value options.
   */
  public static function goals() {
    return [
      'stewardship' => t('Improve my land stewardship'),
      'funding' => t('Access funding for land management practices'),
      'regulation' => t('Get help with permitting, regulations, or a related issue'),
      'economic' => t('Improve economic standing of my farm/ranch'),
      'other' => t('Other'),
    ];
  }

  /**
   * Concerns.
   *
   * @return array
   *   Returns an array of allowed value options.
   */
  public static function concerns() {
    return [
      'soil' => t('Soil'),
      'water' => t('Water'),
      'animals' => t('Animals'),
      'plants' => t('Plants'),
      'air' => t('Air'),
      'human' => t('Human'),
      'energy' => t('Energy'),
      'other' => t('Other'),
    ];
  }

  /**
   * States.
   *
   * @return array
   *   Returns an array of allowed value options.
   */
  public static function states() {
    return [
      'AL' => t('Alabama'),
      'AK' => t('Alaska'),
      'AZ' => t('Arizona'),
      'AR' => t('Arkansas'),
      'CA' => t('California'),
      'CO' => t('Colorado'),
      'CT' => t('Connecticut'),
      'DE' => t('Delaware'),
      'FL' => t('Florida'),
      'GA' => t('Georgia'),
      'HI' => t('Hawaii'),
      'ID' => t('Idaho'),
      'IL' => t('Illinois'),
      'IN' => t('Indiana'),
      'IA' => t('Iowa'),
      'KS' => t('Kansas'),
      'KY' => t('Kentucky'),
      'LA' => t('Louisiana'),
      'ME' => t('Maine'),
      'MD' => t('Maryland'),
      'MA' => t('Massachusetts'),
      'MI' => t('Michigan'),
      'MN' => t('Minnesota'),
      'MS' => t('Mississippi'),
      'MO' => t('Missouri'),
      'MT' => t('Montana'),
      'NE' => t('Nebraska'),
      'NV' => t('Nevada'),
      'NH' => t('New Hampshire'),
      'NJ' => t('New Jersey'),
      'NM' => t('New Mexico'),
      'NY' => t('New York'),
      'NC' => t('North Carolina'),
      'ND' => t('North Dakota'),
      'OH' => t('Ohio'),
      'OK' => t('Oklahoma'),
      'OR' => t('Oregon'),
      'PA' => t('Pennsylvania'),
      'RI' => t('Rhode Island'),
      'SC' => t('South Carolina'),
      'SD' => t('South Dakota'),
      'TN' => t('Tennessee'),
      'TX' => t('Texas'),
      'UT' => t('Utah'),
      'VT' => t('Vermont'),
      'VA' => t('Virginia'),
      'WA' => t('Washington'),
      'WV' => t('West Virginia'),
      'WI' => t('Wisconsin'),
      'WY' => t('Wyoming'),
    ];
  }

}
