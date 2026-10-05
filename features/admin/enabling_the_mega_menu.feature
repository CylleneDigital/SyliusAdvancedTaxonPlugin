@enabling_the_mega_menu
Feature: Enabling the mega menu of a channel
    In order to show a richer navigation in a shop
    As an Administrator
    I want to enable the mega menu of its channel

    Background:
        Given the store operates on a single channel in "United States"
        And I am logged in as an administrator

    @ui
    Scenario: Enabling the mega menu
        When I want to modify a channel "United States"
        And I enable its mega menu
        And I save my changes
        Then I should be notified that it has been successfully edited
        And this channel should have the mega menu enabled
