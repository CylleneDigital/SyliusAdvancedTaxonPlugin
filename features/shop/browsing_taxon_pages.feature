@browsing_taxon_pages
Feature: Browsing the page of a taxon
    In order to find the products of a taxon
    As a Visitor
    I want to see the content the merchant configured on its page

    Background:
        Given the store operates on a single channel in "United States"
        And the store classifies its products as "Watches" with "watches" code
        And the "Watches" taxon has children taxons "Sport" and "Classic"

    @ui
    Scenario: Seeing the featured child taxons
        Given the "Watches" taxon features the "Classic" taxon
        When I browse the "Watches" taxon
        Then the featured child taxon should be "Classic"

    @ui
    Scenario: No featured child taxon block without featured child
        When I browse the "Watches" taxon
        Then I should not see any featured child taxon

    @ui
    Scenario: Seeing the featured products when their display is enabled
        Given the store has a product "Steel watch" belonging to the "Watches" taxon
        And the store has a product "Gold watch" belonging to the "Watches" taxon
        And the "Watches" taxon features the "Gold watch" product
        And the "Watches" taxon displays its featured products
        When I browse the "Watches" taxon
        Then the featured products should be the "Gold watch" product

    @ui
    Scenario: No featured products while their display is disabled
        Given the store has a product "Gold watch" belonging to the "Watches" taxon
        And the "Watches" taxon features the "Gold watch" product
        When I browse the "Watches" taxon
        Then I should not see any featured product

    @ui
    Scenario: Seeing the media of the taxon zones
        Given the store has a product "Steel watch" belonging to the "Watches" taxon
        And the "Watches" taxon has a media titled "Summer sale" in the "top" zone
        And the "Watches" taxon has a media titled "Free engraving" in the "right" zone
        And the "Watches" taxon has a media titled "Our workshop" in the "left" zone
        And the "Watches" taxon has a media titled "Since 1920" in the "bottom" zone
        And the "Watches" taxon has an image without type
        When I browse the "Watches" taxon
        Then I should see the media "Summer sale" in the "top" zone
        And I should see the media "Our workshop" in the "left" zone
        And I should see the media "Since 1920" in the "bottom" zone
        And I should see the media card "Free engraving" in the product list
        And I should see the main image of the taxon

    @ui
    Scenario: Showing the media of the top zone as a slider
        Given the store has a product "Steel watch" belonging to the "Watches" taxon
        And the "Watches" taxon has a media titled "Summer sale" in the "top" zone
        And the "Watches" taxon has a media titled "Free engraving" in the "top" zone
        And the "Watches" taxon shows its top zone as a slider
        When I browse the "Watches" taxon
        Then the "top" zone should be a slider of the media "Summer sale" and "Free engraving"

    @ui
    Scenario: Placing the media cards at the start of the product list
        Given the store has a product "Steel watch" belonging to the "Watches" taxon
        And the store has a product "Gold watch" belonging to the "Watches" taxon
        And the "Watches" taxon has a media titled "Free engraving" in the "right" zone
        And the "Watches" taxon inserts its media cards at the start of the product list
        When I browse the "Watches" taxon
        Then the media card "Free engraving" should be the first item of the product list

    @ui
    Scenario: Placing the media cards among the products
        Given the store has a product "Steel watch" belonging to the "Watches" taxon
        And the store has a product "Gold watch" belonging to the "Watches" taxon
        And the store has a product "Silver watch" belonging to the "Watches" taxon
        And the "Watches" taxon has a media titled "Free engraving" in the "right" zone
        And the "Watches" taxon inserts its media cards in the middle of the product list
        When I browse the "Watches" taxon
        Then the media card "Free engraving" should be neither the first nor the last item of the product list

    @ui
    Scenario: Seeing the media cards in the list view
        Given the store has a product "Steel watch" belonging to the "Watches" taxon
        And the "Watches" taxon has a media titled "Free engraving" in the "right" zone
        When I browse the "Watches" taxon in the list view
        Then I should see the media "Free engraving" among the products of the list view

    @ui
    Scenario: Seeing the taxon name with its color and its icon
        Given the "Watches" taxon has the "#336699" color
        And the "Watches" taxon uses the "tabler:folder" icon
        When I browse the "Watches" taxon
        Then the taxon name should be shown in "#336699" with its icon

    @ui
    Scenario: Hiding the color and the icon on the taxon page
        Given the "Watches" taxon has the "#336699" color
        And the "Watches" taxon uses the "tabler:folder" icon
        And the "Watches" taxon hides its color and icon on its page
        When I browse the "Watches" taxon
        Then the taxon name should be shown without its color nor its icon
