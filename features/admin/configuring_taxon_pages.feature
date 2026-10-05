@configuring_taxon_pages
Feature: Configuring the page of a taxon
    In order to merchandise a taxon page
    As an Administrator
    I want to choose how its featured products show and whether it is a universe

    Background:
        Given the store operates on a single channel
        And the store has locale "English (United States)"
        And the store classifies its products as "Watches" with "watches" code
        And I am logged in as an administrator

    @ui
    Scenario: Displaying the featured products after the filters as a slider
        When I want to modify the "Watches" taxon
        And I display its featured products after the filters as a slider
        And I save my changes
        Then I should be notified that it has been successfully edited
        And the "Watches" taxon should display its featured products after the filters as a slider

    @ui
    Scenario: Turning a taxon into a universe page
        When I want to modify the "Watches" taxon
        And I make it a universe titled "The watch universe" in "English (United States)"
        And I save my changes
        Then I should be notified that it has been successfully edited
        And the "Watches" taxon should be a universe titled "The watch universe"
