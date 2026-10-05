@advanced_taxon @ui
Feature: Managing the advanced customization of a taxon
    In order to customize the storefront taxonomy
    As an Administrator
    I want to configure the advanced options of a taxon

    Background:
        Given the store operates on a single channel
        And the store has locale "English (United States)"
        And I am logged in as an administrator

    Scenario: Adding a taxon with a colour and an icon
        When I want to create a new taxon
        And I specify its code as "watches"
        And I set the taxon name to "Watches" in "English (United States)"
        And I set the taxon slug to "watches" in "English (United States)"
        And I set its color to "#336699"
        And I choose the "watch" icon
        And I add it
        Then I should be notified that it has been successfully created
        And the taxon with code "watches" should have the "#336699" color
        And the taxon with code "watches" should use the "watch" icon

    Scenario: Uploading a pictogram stores it in the application public directory
        When I want to create a new taxon
        And I specify its code as "pictograms"
        And I set the taxon name to "Pictograms" in "English (United States)"
        And I set the taxon slug to "pictograms" in "English (United States)"
        And I upload the "pictogram.png" pictogram
        And I add it
        Then I should be notified that it has been successfully created
        And the pictogram of the taxon with code "pictograms" should be stored in the application public directory

    Scenario: Only product card media offer the card text option
        When I want to create a new taxon
        And I specify its code as "media-zones"
        And I set the taxon name to "Media zones" in "English (United States)"
        And I set the taxon slug to "media-zones" in "English (United States)"
        And I add it
        Then I should be notified that it has been successfully created
        Given the taxon with code "media-zones" has a media in the "right" zone at position 2
        And the taxon with code "media-zones" has a media in the "top" zone at position 3
        When I want to edit the taxon with code "media-zones"
        Then the "right" zone should offer the card text option
        And the "top" zone should not offer the card text option
        And the "left" zone should not offer the card text option
        And the "bottom" zone should not offer the card text option
        And the "featured" zone should not offer the card text option
        And the "slider_univers" zone should not offer the card text option

    Scenario: New media are offered the position following the media already entered
        When I want to create a new taxon
        And I specify its code as "media-positions"
        And I set the taxon name to "Media positions" in "English (United States)"
        And I set the taxon slug to "media-positions" in "English (United States)"
        And I add it
        Then I should be notified that it has been successfully created
        Given the taxon with code "media-positions" has a media in the "right" zone at position 2
        And the taxon with code "media-positions" has a media in the "right" zone at position 5
        When I want to edit the taxon with code "media-positions"
        Then the next position offered by the "right" zone should be 6
        And the "right" zone media positions should be "2,5"
        And the next position offered by the "top" zone should be 1
        And the next position offered by the "slider_univers" zone should be 1
