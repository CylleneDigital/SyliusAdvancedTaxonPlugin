@searching_the_taxon_tree
Feature: Searching a taxon in the tree
    In order to find a taxon in a catalog holding many of them
    As an Administrator
    I want to filter the taxon tree on the name I type

    Background:
        Given the store operates on a single channel
        And the store has locale "English (United States)"
        And the store classifies its products as "At menswear"
        And the "At menswear" taxon has children taxons "At shirts" and "At trousers"
        And I am logged in as an administrator

    @ui @javascript
    Scenario: Filtering the taxon tree on the name of a taxon
        When I want to modify the "At shirts" taxon
        And I search "At shirts" in the taxon tree
        Then the taxon tree should list "At menswear" and "At shirts" only

    @ui @javascript
    Scenario: Searching a taxon the catalog does not hold
        When I want to modify the "At menswear" taxon
        And I search "At watches" in the taxon tree
        Then the taxon tree should say that no taxon matches "At watches"

    @ui @javascript
    Scenario: Clearing the search lists the whole tree again
        When I want to modify the "At menswear" taxon
        And I search "At watches" in the taxon tree
        Then the taxon tree should say that no taxon matches "At watches"
        When I clear the taxon tree search
        Then the taxon tree should list "At menswear", "At shirts" and "At trousers"

    @ui @javascript
    Scenario: A large tree arrives folded down to its roots and opens on a search
        Given the "At menswear" taxon holds 100 child taxons
        When I want to modify the "At menswear" taxon
        Then the taxon tree should list "At menswear" only
        When I search "Sub-taxon 001" in the taxon tree
        Then the taxon tree should list "At menswear" and "Sub-taxon 001" only
        When I clear the taxon tree search
        Then the taxon tree should list "At menswear" only
