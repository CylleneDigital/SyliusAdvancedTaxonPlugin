@materializing_conditional_taxons
Feature: Materializing conditional taxons
    In order to merchandise products automatically
    As a Developer
    I want the products matching a conditional taxon to be assigned to it

    Background:
        Given the store operates on a single channel

    @domain
    Scenario: Saving a conditional taxon assigns the products matching its conditions
        Given the store has a product "Blue watch"
        And the store has a product "Red hat"
        When the taxon "Watches" matches products whose name contains "Watch"
        Then the taxon "Watches" should be conditional
        And the taxon "Watches" should have 1 assigned product
        And the taxon "Watches" should be assigned the product "Blue watch"

    @domain
    Scenario: Reconfiguring a taxon updates its assignments
        Given the store has a product "Blue watch"
        And the store has a product "Red hat"
        And the store has a product "Green hat"
        And the taxon "Headwear" matches products whose name contains "Watch"
        Then the taxon "Headwear" should have 1 assigned product
        When I reconfigure the taxon "Headwear" to match products whose name contains "Hat"
        Then the taxon "Headwear" should have 2 assigned products
        And the taxon "Headwear" should be assigned the product "Red hat"

    @domain
    Scenario: Editing the value of an existing condition updates the assignments
        Given the store has a product "Blue watch"
        And the store has a product "Red hat"
        And the taxon "Accessories" matches products whose name contains "Watch"
        When I change the value of the condition of the taxon "Accessories" to "Hat"
        Then the taxon "Accessories" should have 1 assigned product
        And the taxon "Accessories" should be assigned the product "Red hat"

    @domain
    Scenario: The synchronization command assigns products that became eligible after the last save
        Given the taxon "Watches" matches products whose name contains "Watch"
        And the store has a product "Blue watch"
        Then the taxon "Watches" should have 0 assigned products
        When I run the conditional taxon synchronization command
        Then the taxon "Watches" should have 1 assigned product

    @domain
    Scenario: The synchronization command is idempotent
        Given the store has a product "Blue watch"
        And the taxon "Watches" matches products whose name contains "Watch"
        Then the taxon "Watches" should have 1 assigned product
        When I run the conditional taxon synchronization command
        Then the taxon "Watches" should have 1 assigned product

    @domain
    Scenario: An incomplete condition never assigns the whole catalogue
        Given the store has a product "Blue watch"
        And the store has a product "Red hat"
        When the taxon "Broken" has an attribute condition without attribute
        Then the taxon "Broken" should have 0 assigned products

    @domain
    Scenario: Removing one of the conditions widens the assignments again
        Given the store has a product "Blue watch"
        And the store has a product "Steel watch"
        And the taxon "Watches" matches products whose name contains "Watch"
        And the taxon "Watches" also matches products whose name contains "Blue"
        Then the taxon "Watches" should have 1 assigned product
        When I remove the condition on "Blue" from the taxon "Watches"
        Then the taxon "Watches" should have 2 assigned products

    @domain
    Scenario: Removing every condition detaches the materialized products
        Given the store has a product "Blue watch"
        And the taxon "Watches" matches products whose name contains "Watch"
        Then the taxon "Watches" should have 1 assigned product
        When I remove every condition of the taxon "Watches"
        Then the taxon "Watches" should not be conditional
        And the taxon "Watches" should have 0 assigned products

    @domain
    Scenario: Renaming a conditional taxon does not synchronize it
        Given the store has a product "Blue watch"
        And the store has a product "Red hat"
        And the taxon "Watches" matches products whose name contains "Watch"
        And the product "Red hat" belongs to taxon "Watches"
        When I rename the taxon "Watches" to "Timepieces"
        Then the taxon "Watches" should have 2 assigned products

    @domain
    Scenario: A disabled product keeps its assignment
        Given the store has a product "Blue watch"
        And the taxon "Watches" matches products whose name contains "Watch"
        When the product "Blue watch" is disabled
        And I run the conditional taxon synchronization command
        Then the taxon "Watches" should have 1 assigned product

    @domain
    Scenario: Deleting a conditional taxon removes its assignments
        Given the store has a product "Blue watch"
        And the taxon "Watches" matches products whose name contains "Watch"
        When I delete the taxon "Watches"
        Then the taxon "Watches" should not exist any more
        And the product "Blue watch" should not belong to any taxon
