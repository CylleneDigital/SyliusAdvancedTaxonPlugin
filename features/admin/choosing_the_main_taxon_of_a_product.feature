@choosing_the_main_taxon_of_a_product
Feature: Choosing the main taxon of a product
    In order to keep every product reachable from a product list
    As an Administrator
    I want a universe to be refused as the main taxon of a product

    Background:
        Given the store operates on a single channel
        And the store classifies its products as "Watches" with "watches" code
        And the "Watches" taxon is a universe
        And the store has a product "Steel watch"
        And I am logged in as an administrator

    @ui
    Scenario: A universe cannot be the main taxon of a product
        Given the product "Steel watch" has a main taxon "Watches"
        When I want to modify the "Steel watch" product
        And I save my changes
        Then I should be told that the "Watches" taxon cannot be the main taxon of a product
