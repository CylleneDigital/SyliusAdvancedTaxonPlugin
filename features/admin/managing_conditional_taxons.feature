@managing_conditional_taxons
Feature: Managing the conditions of a taxon
    In order to assign products to a taxon automatically
    As an Administrator
    I want to set its conditions in the taxon form

    Background:
        Given the store operates on a single channel
        And the store has locale "English (United States)"
        And the store has a product "Red cap"
        And the store has a product "Blue cap"
        And the store has a product "Jeans"
        And the store classifies its products as "Caps" with "caps" code
        And I am logged in as an administrator

    @ui @javascript
    Scenario: Previewing the products matching the conditions
        When I want to modify the "Caps" taxon
        And I add a condition on the product name containing "cap"
        And I test the conditions
        Then the conditions preview should list 2 matching products

    @ui @javascript
    Scenario: Saving the conditions assigns the matching products
        When I want to modify the "Caps" taxon
        And I add a condition on the product name containing "cap"
        And I save my changes
        Then I should be notified that it has been successfully edited
        And the taxon "Caps" should be conditional
        And the taxon "Caps" should have 2 assigned products

    @ui @javascript
    Scenario: A condition without its attribute is refused
        When I want to modify the "Caps" taxon
        And I add a condition on an attribute without choosing the attribute
        And I try to save my changes
        Then I should be told to choose the attribute of the condition
        And the conditions tab should be flagged as holding an error
        And the taxon "Caps" should not be conditional
        And the taxon "Caps" should have no condition
        And the taxon "Caps" should have 0 assigned products
