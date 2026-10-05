@managing_taxon_media
Feature: Managing the media of a taxon
    In order to illustrate a taxon page
    As an Administrator
    I want to place media in the zones of the taxon page

    Background:
        Given the store operates on a single channel
        And the store has locale "English (United States)"
        And the store classifies its products as "Watches" with "watches" code
        And I am logged in as an administrator

    @ui
    Scenario: Only product card media offer the card text option
        Given the "Watches" taxon has a media in the "right" zone at position 2
        When I want to modify the "Watches" taxon
        Then the "right" zone should offer the card text option
        And the "main" zone should not offer the card text option
        And the "top" zone should not offer the card text option
        And the "left" zone should not offer the card text option
        And the "bottom" zone should not offer the card text option
        And the "featured" zone should not offer the card text option
        And the "slider_universe" zone should not offer the card text option

    @ui
    Scenario: Only the zones rendering links offer a destination URL
        When I want to modify the "Watches" taxon
        Then the "right" zone should offer a destination URL
        And the "slider_universe" zone should offer a destination URL
        And the "main" zone should not offer a destination URL
        And the "top" zone should not offer a destination URL
        And the "featured" zone should not offer a destination URL

    @ui
    Scenario: New media are offered the position following the media already entered
        Given the "Watches" taxon has a media in the "right" zone at position 2
        And the "Watches" taxon has a media in the "right" zone at position 5
        When I want to modify the "Watches" taxon
        Then the next position offered by the "right" zone should be 6
        And the "right" zone media positions should be "2,5"
        And the next position offered by the "top" zone should be 1

    @ui
    Scenario: The main image saved by Sylius is listed in the main image zone
        Given the "Watches" taxon has an image without type
        When I want to modify the "Watches" taxon
        Then the "main" zone media positions should be "0"

    @ui @javascript
    Scenario: Media added after a removal never take the field names of the media kept
        Given the "Watches" taxon has a media in the "right" zone at position 1
        And the "Watches" taxon has a media in the "right" zone at position 2
        When I want to modify the "Watches" taxon
        And I remove the first media of the "right" zone
        And I add a media to the "right" zone
        And I add a media to the "right" zone
        Then the "right" zone media should have distinct field names
        And the "right" zone media positions should be "2,3,4"
