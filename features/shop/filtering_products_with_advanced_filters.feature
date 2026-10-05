@filtering_products_with_advanced_filters
Feature: Filtering the products of a taxon with advanced filters
    In order to narrow down a long product list
    As a Visitor
    I want to filter it by the values of its products

    Background:
        Given the store operates on a single channel in "United States"
        And the store classifies its products as "Watches" with "watches" code
        And the store has a text product attribute "Material"
        And the store has a product "Steel watch" belonging to the "Watches" taxon
        And this product has a text attribute "Material" with value "Steel"
        And the store has a product "Gold watch" belonging to the "Watches" taxon
        And this product has a text attribute "Material" with value "Gold"

    @ui
    Scenario: Seeing the values of the products and their count
        Given the "Watches" taxon has advanced filters
        When I browse the "Watches" taxon
        Then I should see the advanced filters
        And the "Material" filter should offer "Steel" for 1 product
        And the "Material" filter should offer "Gold" for 1 product

    @ui
    Scenario: Filtering the products by a value
        Given the "Watches" taxon has advanced filters
        When I browse the "Watches" taxon
        And I filter the products by "Steel" in the "Material" filter
        Then I should see the "Steel watch" product in the product list
        And I should not see the "Gold watch" product in the product list

    @ui
    Scenario: Searching in the product list keeps the advanced filters
        Given the "Watches" taxon has advanced filters
        When I browse the "Watches" taxon
        And I filter the products by "Steel" in the "Material" filter
        And I search for "watch" in the product list
        Then I should see the "Steel watch" product in the product list
        And I should not see the "Gold watch" product in the product list
        When I clear the product search
        Then I should see the "Steel watch" product in the product list
        And I should not see the "Gold watch" product in the product list

    @ui
    Scenario: No advanced filters unless the taxon enables them
        When I browse the "Watches" taxon
        Then I should not see the advanced filters
