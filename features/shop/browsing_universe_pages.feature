@browsing_universe_pages
Feature: Browsing a universe page
    In order to discover a whole universe of products
    As a Visitor
    I want to see a showcase of its child taxons instead of a product list

    Background:
        Given the store operates on a single channel in "United States"
        And the store classifies its products as "Watches" with "watches" code
        And the "Watches" taxon has children taxons "Sport" and "Classic"
        And the "Watches" taxon is a universe

    @ui
    Scenario: Seeing the child taxons instead of a product list
        Given the "Watches" taxon has the universe page title "The watch universe"
        When I browse the "Watches" taxon
        Then the universe title should be "The watch universe"
        And the universe should present the child taxons "Sport" and "Classic"
        And I should not see any product list

    @ui
    Scenario: Seeing the featured products of the child taxons
        Given the store has a product "Diver watch" belonging to the "Sport" taxon
        And the "Sport" taxon features the "Diver watch" product
        And the "Sport" taxon displays its featured products
        When I browse the "Watches" taxon
        Then the universe should present the "Diver watch" product among the featured products of the "Sport" taxon
