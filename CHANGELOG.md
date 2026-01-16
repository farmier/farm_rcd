# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- [Add a checkbox to intake form to prevent email to stakeholder (only visible to staff) #99](https://github.com/farmier/farm_rcd/issues/99)
- [Provide a form for emailing RCP documents #54](https://github.com/farmier/farm_rcd/issues/54)
- [Add "Unknown" option to lease/own question #124](https://github.com/farmier/farm_rcd/issues/124)
- [Add demographic options to intake form: Family farm, LGBTQ+, Other #117](https://github.com/farmier/farm_rcd/issues/117)

### Changed

- [Make intake fields optional #112](https://github.com/farmier/farm_rcd/issues/112)

### Fixed

- [Fix access to View of plans by farm #120](https://github.com/farmier/farm_rcd/issues/120)
- [Remove stray period prepended to numbered outline list items in RCP template #121](https://github.com/farmier/farm_rcd/issues/121)

### Removed

- [Remove "Not applicable" demographic option from intake form #118](https://github.com/farmier/farm_rcd/issues/118)

## [1.1.0] 2026-01-07

### Added

- [Add acreage/linear ft to practice implementation plans #60](https://github.com/farmier/farm_rcd/issues/60)
- [View of plans by farm #38](https://github.com/farmier/farm_rcd/issues/38)
- [Auto-populate practice descriptions #86](https://github.com/farmier/farm_rcd/issues/86)
- [Allow staff to edit farms/assets/logs/plans #101](https://github.com/farmier/farm_rcd/issues/101)

### Changed

- [Make property description fields optional #95](https://github.com/farmier/farm_rcd/issues/95)
- [Make land use area description field optional #96](https://github.com/farmier/farm_rcd/issues/96)
- [Prevent changing the practice of an implementation plan #113](https://github.com/farmier/farm_rcd/issues/113)
- [Prevent changing farm/property/land/intake relationships on plans #102](https://github.com/farmier/farm_rcd/issues/102)

## [1.0.1] 2025-12-30

### Fixed

- [Adding/updating a single practice removes other practices from an RCP #94](https://github.com/farmier/farm_rcd/issues/94)

## [1.0.0] 2025-12-11

### Added

The following features are added on top of the existing farmOS data model and
user interface:

- New fields on land assets (for property descriptions):
  - APN: `rcd_apn` (multivalue string)
  - Riparian areas: `rcd_riparian_areas` (string_long)
  - Wildlife: `rcd_wildlife` (string_long)
- Intake Log (`rcd_intake`)
  - Attributes:
    - Stakeholder name: `intake_stakeholder_name` (string)
    - Stakeholder email: `intake_stakeholder_email` (email)
    - Stakeholder phone: `intake_stakeholder_phone` (string)
    - Stakeholder street: `intake_stakeholder_street` (string)
    - Stakeholder city: `intake_stakeholder_city` (string)
    - Stakeholder state: `intake_stakeholder_state` (string)
    - Stakeholder zip: `intake_stakeholder_zip` (string)
    - Stakeholder type: `intake_stakeholder_type` (list_string) w/ options:
      - Manager (`manager`)
      - Municipality (`Municipality`)
      - Landowner (`Landowner`)
      - Lessee (`Lessee`)
    - Stakeholder group: `intake_stakeholder_group` (list_string) w/ options:
      - Beginning farmer or rancher (less than 10 years) (`beginning`)
      - Female (`female`)
      - Veteran (`veteran`)
      - Black or African American (`black`)
      - American Indian or Alaska Native (`native`)
      - Hispanic or Latino (`hispanic`)
      - Asian (`asian`)
      - Pacific Islander (`pacific`)
      - Not applicable (`na`)
      - Prefer not to answer (`optout`)
    - Farm name: `intake_farm_name` (string)
    - Own or lease: `intake_stakeholder_own_or_lease` (list_string) w/ options:
      - Own (`own`)
      - Lease (`lease`)
    - Property owner: `intake_property_owner` (string)
    - Lease expiration: `intake_stakeholder_lease_exp` (timestamp)
    - Property acreage: `intake_property_acreage` (decimal)
    - Property street: `intake_property_street` (string)
    - Property city: `intake_property_city` (string)
    - Property state: `intake_property_state` (string)
    - Property zip: `intake_property_zip` (string)
    - Parcel number or GPS coordinates: `intake_property_parcel_gps` (string)
    - Land use: `intake_property_use` (list_string) w/ options:
      - Grazing (`grazing`)
      - Vineyards (`vineyards`)
      - Orchards (`orchards`)
      - Row crops (`rowcrops`)
      - Natural lands (`natural`)
      - Other (`other`)
    - Grazing land use acreage: `intake_property_use_grazing_ac` (decimal)
    - Vineyards land use acreage: `intake_property_use_vineyard_ac` (decimal)
    - Orchards land use acreage: `intake_property_use_orchard_ac` (decimal)
    - Row crops land use acreage: `intake_property_use_rowcrop_ac` (decimal)
    - Natural land use acreage: `intake_property_use_natural_ac` (decimal)
    - Other land use: `intake_property_use_other` (string)
    - Other land use acreage: `intake_property_use_other_ac` (decimal)
    - Crop types: `intake_property_use_crop_types` (string)
    - Goals: `intake_goals` (list_string) w/ options:
      - Improve my land stewardship (`stewardship`)
      - Access funding for land management practices (`funding`)
      - Get help with permitting, regulations, or a related issue
        (`regulation`)
      - Improve economic standing of my farm/ranch (`economic`)
      - Other (`other`)
    - Other goals: `intake_goals_other` (string)
    - Interests: `intake_interests` (list_string) w/ options:
      - Soil (`soil`)
      - Water (`water`)
      - Animals (wildlife or livestock) (`animals`)
      - Plants (crops or native vegetation) (`plants`)
      - Air (`air`)
      - Human (`human`)
      - Energy (`energy`)
      - Other (`other`)
    - Other concerns: `intake_concerns_other` (string)
    - Additional comments: `intake_comments` (string_long)
    - Allow sharing the application information with other RCDs:
      `intake_rcd_sharing_allowed` (boolean)
- Site Assessment Log (`rcd_site_assessment`)
  - Attributes:
    - Land use history: `rcd_land_use_history` (string_long)
    - Existing infrastructure: `rcd_infrastructure` (string_long)
    - Priority environmental concerns: `rcd_priority_concerns` (string_long)
    - Watersheds: `rcd_watershed` (taxonomy term reference)
    - Groundwater basins: `rcd_groundwater_basin` (taxonomy term reference)
    - Wildlife species: `rcd_wildlife_species` (taxonomy term reference)
    - Plant species: `rcd_plant_species` (taxonomy term reference)
    - Landowner objectives: `rcd_landowner_objectives` (string_long)
    - Soil rating: `rcd_soil_rating` (integer)
    - Soil baseline conditions: `rcd_soil_baseline` (string_long)
    - Soil goals: `rcd_soil_goals` (string_long)
    - Soil strategy to achieve goals: `rcd_soil_strategy` (string_long)
    - Water rating: `rcd_water_rating` (integer)
    - Water baseline conditions: `rcd_water_baseline` (string_long)
    - Water goals: `rcd_water_goals` (string_long)
    - Water strategy to achieve goals: `rcd_water_strategy` (string_long)
    - Plant/vegetation rating: `rcd_plant_rating` (integer)
    - Plant/vegetation baseline conditions: `rcd_plant_baseline` (string_long)
    - Plant/vegetation goals: `rcd_plant_goals` (string_long)
    - Plant/vegetation strategy to achieve goals: `rcd_plant_strategy`
      (string_long)
    - Aquatic habitat rating: `rcd_aquatic_rating` (integer)
    - Aquatic habitat baseline conditions: `rcd_aquatic_baseline` (string_long)
    - Aquatic habitat goals: `rcd_aquatic_goals` (string_long)
    - Aquatic habitat strategy to achieve goals: `rcd_aquatic_strategy`
      (string_long)
    - Livestock rating: `rcd_livestock_rating` (integer)
    - Livestock baseline conditions: `rcd_livestock_baseline` (string_long)
    - Livestock goals: `rcd_livestock_goals` (string_long)
    - Livestock strategy to achieve goals: `rcd_livestock_strategy`
      (string_long)
    - Wildlife rating: `rcd_wildlife_rating` (integer)
    - Wildlife baseline conditions: `rcd_wildlife_baseline` (string_long)
    - Wildlife goals: `rcd_wildlife_goals` (string_long)
    - Wildlife strategy to achieve goals: `rcd_wildlife_strategy` (string_long)
    - Infrastructure rating: `rcd_infrastructure_rating` (integer)
    - Infrastructure baseline conditions: `rcd_infrastructure_baseline`
      (string_long)
    - Infrastructure goals: `rcd_infrastructure_goals` (string_long)
    - Infrastructure strategy to achieve goals: `rcd_infrastructure_strategy`
      (string_long)
- Resource Conservation Plan (`rcd_rcp`)
  - Relationships:
    - Farm: `farm` (farm organization reference)
    - Property: `property` (land asset reference)
    - Intake: `intake` (intake log reference)
    - Practice implementation plans: `practice_implementation_plan` (practice
      implementation plan reference)
- Practice Implementation Plan (`rcd_practice_implementation`)
  - Attributes:
    - Conservation practice: `rcd_practice` (list_string) w/ options:
      - Brush Management (NRCS code 314)
      - Conservation Cover (NRCS code 327)
      - Constructed Wetland (NRCS code 656)
      - Contour Orchard and Perennials (NRCS code 331)
      - Cover Crop (NRCS code 340)
      - Critical Area Planting (NRCS code 342)
      - Filter Strip (NRCS code 393)
      - Forest Stand Management (NRCS code 666)
      - Fuel Break (NRCS code 383)
      - Grassed Waterway (NRCS code 412)
      - Hedgerow Planting (NRCS code 422)
      - Irrigation Water Management (NRCS code 449)
      - Keyline Plow
      - Mulching (NRCS code 484)
      - Nutrient Management (NRCS code 590)
      - Pasture and Hay Planting (NRCS code 512)
      - Prescribed Burn (NRCS code 338)
      - Prescribed Grazing (NRCS code 528)
      - Range Planting (NRCS code 550)
      - Residue and Tillage Management, No Till (NRCS code 345)
      - Restoration of Rare or Declining Natural Communities (NRCS code 643)
      - Riparian Forest Buffer (NRCS code 391)
      - Riparian Herbaceous Planting (NRCS code 390)
      - Roof Runoff Structure (NRCS code 558)
      - Silvopasture (NRCS code 381)
      - Soil Carbon Amendment (NRCS code 336)
      - Stream Habitat Improvement and Management (NRCS code 395)
      - Streambank/Shoreline Improvement (NRCS code 580)
      - Structure for Water Control (NRCS code 587)
      - Structures for Wildlife (NRCS code 649)
      - Tree/Shrub Establishment (NRCS code 612)
      - Upland Wildlife Management (NRCS code 645)
      - Water and Sediment Control Basin (NRCS code 638)
      - Wildlife Habitat Planting (NRCS code 420)
      - Windbreak/Shelterbelt (NRCS code 380)
      - Other
    - Funding source: `rcd_funding_source` (string)
  - Relationships:
    - Farm: `farm` (farm organization reference)
    - Land asset: `land` (land asset reference)
  - Workflow states:
    - Planning (`planning`)
    - Awaiting funding (`awaiting_funding`)
    - Implementing (`implementing`)
    - Review (`review`)
    - Done (`done`)
    - Abandoned (`abandoned`)
- Taxonomies:
  - Groundwater basin (`rcd_groundwater_basin`)
  - Plant species (`rcd_plant_species`)
  - Watershed (`rcd_watershed`)
  - Wildlife species (`rcd_wildlife_species`)
- Land types:
  - Grazing (`rcd_grazing`)
  - Natural Area (`rcd_natural_area`)
  - Orchard (`rcd_orchard`)
  - Pastureland (`rcd_pastureland`)
  - Riparian Area (`rcd_riparian_area`)
  - Row Crops (`rcd_row_crops`)
  - Vineyard (`rcd_vineyard`)
- Intake form (`farm_rcd_intake_form`)
  - Multistep form for collecting stakeholder application information
  - Creates an `rcd_intake` log with a status of `pending`
  - Available to anonymous users
  - Flood control that restricts anonymous submissions to 1 per hour
- Intake review form (`farm_rcd_intake_review_form`)
  - Appears at the top of pending intake logs
  - Assign intake to a staff user.
  - Creates a new farm organization, or choose an existing.
  - Creates a new Resource conservation plan, linked to the intake, farm, and
    user.
  - Ability to abandon the intake with a reason.
- Plan development workflow forms
  - Property description form (`farm_rcd_property_form`)
    - Creates a new property land asset, or choose an existing one.
    - Links property land asset to the Resource conservation plan.
    - Collects basic property information.
  - Land use areas form (`farm_rcd_locations_form`)
    - Creates new land assets to represent land use areas, as children of the
      property land asset.
  - Site assessments form (`farm_rcd_site_assessment_form`)
    - Creates new Site assessment logs linked to land assets.
  - Conservation practices form (`farm_rcd_practice_form`)
    - Creates new Practice implementation plans linked to the Resource
      consveration plan.
  - Document form (`farm_rcd_document_form`)
    - Button for generating a document from template.
    - File upload field for saving the final document.
  - Plan status form (`farm_rcd_status_form`)
    - Buttons for changing the status of the plan.
- Settings form
  - Logo path (`farm_rcd.settings.logo_path`)
  - Intake notification email (`intake_email`)
- Document generator service (`rcd.document.generator`)
  - Takes a DOCX template file path, desired filename, and array of contextual
    information.
  - Dispatches an event that allows modules to add placeholder replacements.
  - Placeholder replacement logic.
  - Generates a DOCX file.
  - Returns a file entity.
- Default RCP template and event subscriber to populate it from RCP context.
- View of farm organizations on dashboard (`farm_rcd_farm_organizations`)
- View of pending intakes on dashboard (`farm_rcd_intakes`)
- Staff user role (`rcd_staff`) w/ permissions:
  - `access asset collection`
  - `access asset overview`
  - `access content`
  - `access farm dashboard`
  - `access log collection`
  - `access organization collection`
  - `access plan collection`
  - `access quantity collection`
  - `access toolbar`
  - `access user profiles`
  - `change own username`
  - `delete own files`
  - `use text format default`
  - `view all asset revisions`
  - `view all log revisions`
  - `view all organization revisions`
  - `view all plan revisions`
  - `view all quantity revisions`
  - `view any asset`
  - `view any farm organization`
  - `view any land asset`
  - `view any log`
  - `view any organization`
  - `view any plan`
  - `view any quantity`
  - `view any rcd_intake log`
  - `view any rcd_practice_implementation plan`
  - `view any rcd_rcp plan`
  - `view any rcd_site_assessment log`
  - `view asset_type`
  - `view flag`
  - `view land_type`
  - `view log_type`
  - `view own asset`
  - `view own farm organization`
  - `view own land asset`
  - `view own log`
  - `view own organization`
  - `view own plan`
  - `view own quantity`
  - `view own rcd_intake log`
  - `view own rcd_practice_implementation plan`
  - `view own rcd_rcp plan`
  - `view own rcd_site_assessment log`
  - `view plan_type`
  - `view quantity_type`
  - `view tag_type`
- Automated tests and GitHub Actions workflow.

### Removed

The following elements are removed/hidden from the default farmOS UI:

- Hide default farmOS dashboard panes:
  - `dashboard_map`
  - `upcoming_tasks`
  - `late_tasks`
  - `metrics`
- Hide default farmOS dashboard buttons:
  - Add asset
  - Add log
  - Add organization
  - Add plan
- Hide the breadcrumb region for anonymous users, to simplify the intake form.

[Unreleased]: https://github.com/farmier/farm_rcd/compare/1.1.0...1.x
[1.1.0]: https://github.com/farmier/farm_rcd/releases/tag/1.1.0
[1.0.1]: https://github.com/farmier/farm_rcd/releases/tag/1.0.1
[1.0.0]: https://github.com/farmier/farm_rcd/releases/tag/1.0.0
