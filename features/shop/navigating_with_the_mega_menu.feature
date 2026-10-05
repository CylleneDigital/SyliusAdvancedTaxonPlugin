@navigating_with_the_mega_menu
Feature: Navigating with the mega menu
    In order to reach a taxon quickly
    As a Visitor
    I want a menu showing the taxons the merchant highlights

    Background:
        Given the store operates on a single channel in "United States"
        And the store classifies its products as "Category" with "category" code
        And the "Category" taxon has child taxon "Watches"
        And the "Watches" taxon has children taxons "Sport" and "Classic"
        And channel "United States" has menu taxon "Category"

    @ui
    Scenario: Seeing the featured child taxons in the mega menu
        Given this channel has the mega menu enabled
        And the "Watches" taxon features the "Classic" taxon
        When I open the shop homepage
        Then the mega menu should be displayed
        And the mega menu should feature the "Classic" taxon under the "Watches" taxon

    @ui
    Scenario: The Sylius menu stays while the channel does not enable the mega menu
        When I open the shop homepage
        Then the mega menu should not be displayed

    @ui
    Scenario: Seeing the color of a taxon in the menu
        Given this channel has the mega menu enabled
        And the "Watches" taxon has the "#336699" color
        When I open the shop homepage
        Then the "Watches" taxon should be shown in "#336699" in the menu

    @ui
    Scenario: Hiding the color of a taxon in the menu
        Given this channel has the mega menu enabled
        And the "Watches" taxon has the "#336699" color
        And the "Watches" taxon hides its color and icon in the menu
        When I open the shop homepage
        Then the "Watches" taxon should be shown without its color in the menu
