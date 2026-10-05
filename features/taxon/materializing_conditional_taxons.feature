@advanced_taxon @conditional
Feature: Materializing conditional taxons
    In order to merchandise products automatically
    As a Developer
    I want the products matching a conditional taxon to be assigned to it

    Background:
        Given the store operates on a single channel

    Scenario: Saving a conditional taxon assigns the products matching its conditions
        Given the store has a product "Blue watch"
        And the store has a product "Red hat"
        When the taxon "Watches" matches products whose name contains "Watch"
        Then the taxon "Watches" should be conditional
        And the taxon "Watches" should have 1 assigned product
        And the taxon "Watches" should be assigned the product "Blue watch"

    Scenario: Reconfiguring a taxon updates its assignments
        Given the store has a product "Blue watch"
        And the store has a product "Red hat"
        And the store has a product "Green hat"
        And the taxon "Headwear" matches products whose name contains "Watch"
        Then the taxon "Headwear" should have 1 assigned product
        When I reconfigure the taxon "Headwear" to match products whose name contains "Hat"
        Then the taxon "Headwear" should have 2 assigned products
        And the taxon "Headwear" should be assigned the product "Red hat"

    Scenario: The synchronization command assigns products that became eligible after the last save
        Given the taxon "Watches" matches products whose name contains "Watch"
        And the store has a product "Blue watch"
        Then the taxon "Watches" should have 0 assigned products
        When I run the conditional taxon synchronization command
        Then the taxon "Watches" should have 1 assigned product

    Scenario: The synchronization command is idempotent
        Given the store has a product "Blue watch"
        And the taxon "Watches" matches products whose name contains "Watch"
        Then the taxon "Watches" should have 1 assigned product
        When I run the conditional taxon synchronization command
        Then the taxon "Watches" should have 1 assigned product
